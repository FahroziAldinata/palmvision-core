<?php

namespace App\Notifications;

use App\Domain\Organisasi\Models\Blok;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaksasiDeviationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Blok $blok,
        public string $tanggal,
        public float $estimasiKg,
        public float $realisasiKg,
        public float $persentaseSelisih
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[PalmVision Alert] Deviasi Taksasi > 15% - Blok {$this->blok->kode_blok}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Terdeteksi selisih signifikan antara taksasi dan realisasi panen di Blok {$this->blok->kode_blok}.")
            ->line("Tanggal: {$this->tanggal}")
            ->line('Estimasi Taksasi: '.number_format($this->estimasiKg, 2, ',', '.').' kg')
            ->line('Realisasi Panen: '.number_format($this->realisasiKg, 2, ',', '.').' kg')
            ->line("Persentase Deviasi: {$this->persentaseSelisih}%")
            ->action('Lihat Dashboard', url('/dashboard'))
            ->line('Harap lakukan evaluasi akurasi taksasi dan investigasi kehilangan hasil (losses/restan) di lapangan.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'taksasi_deviation',
            'title' => 'Deviasi Taksasi > 15%',
            'message' => "Blok {$this->blok->kode_blok} mengalami deviasi sebesar {$this->persentaseSelisih}% (Taksasi: {$this->estimasiKg} kg vs Realisasi: {$this->realisasiKg} kg).",
            'blok_id' => $this->blok->id,
            'kode_blok' => $this->blok->kode_blok,
            'tanggal' => $this->tanggal,
            'estimasi_kg' => $this->estimasiKg,
            'realisasi_kg' => $this->realisasiKg,
            'selisih_persen' => $this->persentaseSelisih,
        ];
    }
}
