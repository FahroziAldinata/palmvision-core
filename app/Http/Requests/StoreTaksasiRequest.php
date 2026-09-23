<?php

namespace App\Http\Requests;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaksasiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Taksasi::class) ?? false;
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
            'tanggal_taksasi' => ['required', 'date'],
            'pokok_disampel' => ['required', 'integer', 'min:1'],
            'estimasi_janjang' => ['required', 'integer', 'min:1'],
            'estimasi_bjr' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Custom validation logic for scoping.
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
                $blokId = (string) $this->input('blok_id');

                $blok = Blok::find($blokId);
                if (! $blok) {
                    return;
                }

                // Scoping: kerani taksasi hanya boleh mencatat pada blok di afdelingnya
                if ($user->hasRole('kerani_taksasi') && $user->afdeling_id !== $blok->afdeling_id) {
                    $validator->errors()->add(
                        'blok_id',
                        'Anda hanya dapat menginput taksasi untuk blok di afdeling Anda.'
                    );
                }
            },
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blok_id.required' => 'Blok taksasi wajib dipilih.',
            'blok_id.exists' => 'Blok taksasi tidak valid.',
            'tanggal_taksasi.required' => 'Tanggal taksasi wajib diisi.',
            'pokok_disampel.required' => 'Jumlah pokok sampel wajib diisi.',
            'pokok_disampel.min' => 'Pokok sampel minimal 1.',
            'estimasi_janjang.required' => 'Estimasi janjang sampel wajib diisi.',
            'estimasi_janjang.min' => 'Estimasi janjang minimal 1.',
            'estimasi_bjr.required' => 'Estimasi BJR wajib diisi.',
            'estimasi_bjr.min' => 'Estimasi BJR minimal 0.1 Kg.',
        ];
    }
}
