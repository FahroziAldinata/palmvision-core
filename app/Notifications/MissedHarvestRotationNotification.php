<?php

namespace App\Notifications;

use App\Domain\Organisasi\Models\Blok;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissedHarvestRotationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Blok $blok,
        public string $tanggalRotasiSeharusnya,
        public int $hariTerlewat
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
            ->subject("[PalmVision Alert] Rotasi Panen Terlewat - Blok {$this->blok->kode_blok}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Blok {$this->blok->kode_blok} teridentifikasi telah melewati siklus rotasi panen yang dijadwalkan.")
            ->line("Jadwal Rotasi Seharusnya: {$this->tanggalRotasiSeharusnya}")
            ->line("Keterlambatan: {$this->hariTerlewat} hari")
            ->action('Buka Peta GIS Blok', url('/gis'))
            ->line('Segera alokasikan pemanen ke blok ini untuk mencegah TBS lewat matang (overripe).');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'missed_rotation',
            'title' => 'Rotasi Panen Terlewat',
            'message' => "Blok {$this->blok->kode_blok} melewati jadwal rotasi panen ({$this->hariTerlewat} hari terlewat sejak {$this->tanggalRotasiSeharusnya}).",
            'blok_id' => $this->blok->id,
            'kode_blok' => $this->blok->kode_blok,
            'tanggal_rotasi_seharusnya' => $this->tanggalRotasiSeharusnya,
            'hari_terlewat' => $this->hariTerlewat,
        ];
    }
}
