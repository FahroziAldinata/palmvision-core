<?php

namespace App\Http\Requests;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProduksiHarianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProduksiHarian::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'blok_id' => ['required', 'uuid', 'exists:blok,id'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pemanen_id' => ['required', 'uuid', 'distinct', 'exists:pemanen,id'],
            'items.*.jumlah_janjang' => ['required', 'integer', 'min:1'],
            'items.*.berat_kg' => ['required', 'numeric', 'min:0.1'],
        ];
    }

    /**
     * Custom validation logic for scoping and validation status.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();
                $blokId = $this->input('blok_id');
                $tanggal = $this->input('tanggal');

                // 1. Scoping Check: Mandor hanya boleh input blok di afdelingnya
                $blok = Blok::find($blokId);
                if (! $blok || $user->afdeling_id !== $blok->afdeling_id) {
                    $validator->errors()->add(
                        'blok_id',
                        'Anda tidak memiliki wewenang untuk mencatat produksi pada blok di luar afdeling Anda.'
                    );

                    return;
                }

                // 2. Harvester scoping: pemanen harus berada di afdeling yang sama
                /** @var array<int, array{pemanen_id?: string}> $items */
                $items = (array) $this->input('items', []);
                $pemanenIds = array_values(array_filter(array_column($items, 'pemanen_id')));
                $validPemanenCount = Pemanen::whereIn('id', $pemanenIds)
                    ->where('afdeling_id', $user->afdeling_id)
                    ->count();

                if ($validPemanenCount !== count($pemanenIds)) {
                    $validator->errors()->add(
                        'items',
                        'Daftar pemanen harus berasal dari pekerja aktif di afdeling Anda.'
                    );

                    return;
                }

                // 3. Cek jika data sudah ada dan sudah disetujui (tidak boleh diubah lagi)
                $existing = ProduksiHarian::where('blok_id', $blokId)
                    ->where('tanggal', $tanggal)
                    ->where('dicatat_oleh', $user->id)
                    ->first();

                if ($existing && $existing->status_validasi === 'disetujui') {
                    $validator->errors()->add(
                        'blok_id',
                        'Entri produksi untuk blok dan tanggal ini sudah tervalidasi dan tidak dapat diubah lagi.'
                    );
                }
            },
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blok_id.required' => 'Blok kebun wajib dipilih.',
            'blok_id.exists' => 'Blok kebun yang dipilih tidak valid.',
            'tanggal.required' => 'Tanggal panen wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal panen tidak boleh melebihi hari ini.',
            'items.required' => 'Minimal satu baris pemanen harus diinput.',
            'items.min' => 'Minimal satu baris pemanen harus diinput.',
            'items.*.pemanen_id.required' => 'Pemanen wajib dipilih.',
            'items.*.pemanen_id.distinct' => 'Pemanen tidak boleh ganda dalam satu form entri.',
            'items.*.jumlah_janjang.required' => 'Jumlah janjang wajib diisi.',
            'items.*.jumlah_janjang.min' => 'Jumlah janjang minimal 1.',
            'items.*.berat_kg.required' => 'Berat kg wajib diisi.',
            'items.*.berat_kg.min' => 'Berat kg minimal 0.1.',
        ];
    }
}
