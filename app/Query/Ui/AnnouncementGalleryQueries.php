<?php

declare(strict_types=1);

namespace App\Query\Ui;

use App\Enums\Notification\AnnouncementPriority;
use App\Models\Notification\Announcement;
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;

/**
 * UI Deneme > Liste denemeleri (D-139): duyuru listesi yerlesim denemelerinin
 * verisi. Bu ortamda duyuru varsa son duyurular, yoksa ornek duyurular
 * (kart denemesindeki ornek sunucular gibi) kullanilir. Her kayit dizi olarak
 * doner; denemeler dizi verili tablo ve sema bilesenleriyle cizilir.
 */
final class AnnouncementGalleryQueries
{
    /**
     * @return array{items: list<array<string, mixed>>, sample: bool}
     */
    public function items(int $limit = 6): array
    {
        $real = Announcement::query()
            ->with('sender')
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get();

        if ($real->isNotEmpty()) {
            return [
                'items' => $real->map(fn (Announcement $announcement): array => $this->item(
                    id: (int) $announcement->getKey(),
                    title: (string) $announcement->title,
                    body: (string) $announcement->body,
                    priority: $announcement->priority ?? AnnouncementPriority::Normal,
                    sender: (string) ($announcement->sender?->full_name ?? __('activity.system')),
                    audience: (string) ($announcement->audience_label ?? '-'),
                    recipients: (int) $announcement->recipient_count,
                    sentAt: Carbon::parse($announcement->sent_at),
                    actionUrl: $announcement->action_url,
                ))->values()->all(),
                'sample' => false,
            ];
        }

        return ['items' => array_slice($this->samples(), 0, $limit), 'sample' => true];
    }

    /**
     * Ornek duyurular: farkli uzunluk ve onemde, bugun ve onceki gunlere
     * yayilmis (gune gore gruplama ve "1 gun once" gorunsun diye).
     *
     * @return list<array<string, mixed>>
     */
    private function samples(): array
    {
        $today = Carbon::now(DisplayTime::zone())->startOfDay();

        return [
            $this->item(1, 'Bu akşam sistem bakımı', 'Bu akşam 22:00 – 23:30 arasında sistem bakımda olacak. Açık formlarınızı bu saatten önce kaydedin; bakım süresince giriş yapılamaz.', AnnouncementPriority::Urgent, 'Ömer Faruk Ermiş', 'Tüm personel', 42, $today->copy()->setTime(9, 10)),
            $this->item(2, 'Sistem güncellemesi hakkında', 'İş Panosu ve Kontrol matrisi sağ üst navigation bar alanına taşınmış, sol menüden kaldırılmıştır.', AnnouncementPriority::Normal, 'Ömer Faruk Ermiş', '5 kişi', 5, $today->copy()->subDay()->setTime(16, 47)),
            $this->item(3, 'Ekim ayı İSG eğitimi', 'Tüm saha personelinin 3 Ekim Cuma 09:30\'da Karapınar şantiyesindeki İSG eğitimine katılması zorunludur. Katılım listesi İnsan Kaynakları tarafından tutulacak; eğitime katılmayanlar sahaya alınmayacaktır.', AnnouncementPriority::Important, 'Hüseyin Güneş', 'Saha personeli', 18, $today->copy()->subDay()->setTime(10, 15)),
            $this->item(4, 'Yeni teklif mektubu şablonu', 'Teklif mektupları için şablonun 3. sürümü yayında. Eski şablon 15 Ekim\'e kadar kullanılabilir; sonrasında yalnız yeni sürüm kabul edilecek.', AnnouncementPriority::Normal, 'Ersin Özdemir', 'İş Geliştirme', 8, $today->copy()->subDays(2)->setTime(11, 20)),
            $this->item(5, 'Haftalık raporlar Cuma 17:00\'ye kadar', 'Haftalık raporların İş panosundaki "Haftalık rapora dönüştür" ile Cuma 17:00\'ye kadar gönderilmesi gerekiyor. Geciken raporlar amirlere bildirim olarak düşer.', AnnouncementPriority::Important, 'Yusuf Gökcan Fil', 'Yöneticiler', 12, $today->copy()->subDays(3)->setTime(14, 30)),
            $this->item(6, '29 Ekim Cumhuriyet Bayramı', '28 Ekim öğleden sonra ve 29 Ekim resmî tatildir. Şantiyelerdeki nöbet listesi proje müdürleri tarafından paylaşılacaktır.', AnnouncementPriority::Normal, 'Ömer Faruk Ermiş', 'Tüm personel', 42, $today->copy()->subDays(5)->setTime(9, 0)),
        ];
    }

    /**
     * Denemelerin ortak kayit bicimi. tone: onem rengi; Normal gri, Onemli
     * sari, Acil kirmizi (kirmizi yalniz acile ayrilir).
     *
     * @return array<string, mixed>
     */
    private function item(
        int $id,
        string $title,
        string $body,
        AnnouncementPriority $priority,
        string $sender,
        string $audience,
        int $recipients,
        Carbon $sentAt,
        ?string $actionUrl = null,
    ): array {
        $local = $sentAt->copy()->setTimezone(DisplayTime::zone())->locale(app()->getLocale());
        $today = Carbon::now(DisplayTime::zone())->startOfDay();

        return [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
            'priority_label' => $priority->getLabel(),
            'tone' => $priority->listTone(),
            'sender' => $sender,
            'audience' => $audience,
            'recipients' => $recipients,
            'sent_at' => $local,
            'date' => $local->format('d.m.Y H:i'),
            'time' => $local->format('H:i'),
            'day_short' => $local->translatedFormat('j M'),
            'since' => $local->diffForHumans(),
            'day_key' => $local->format('Y-m-d'),
            'day_label' => match (true) {
                $local->isSameDay($today) => __('ui_gallery.lists.today'),
                $local->isSameDay($today->copy()->subDay()) => __('ui_gallery.lists.yesterday'),
                default => $local->translatedFormat('j F l'),
            },
            'meta' => $sender.' · '.$audience,
            'meta_full' => $local->format('d.m.Y H:i').' · '.$sender.' · '.$audience,
            'action_url' => $actionUrl,
        ];
    }
}
