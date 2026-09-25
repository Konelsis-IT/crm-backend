<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Policies\PanelNotificationPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Zil bildirimi (Laravel `notifications` tablosu). "Tum bildirimler"
 * tablosu (D-122) bu model uzerinden okur; kayit bicimini Filament
 * belirler (`data`: title, body, icon, iconColor, actions[].url).
 */
#[UsePolicy(PanelNotificationPolicy::class)]
class PanelNotification extends DatabaseNotification
{
    public function title(): string
    {
        return $this->plain($this->data['title'] ?? null);
    }

    public function body(): string
    {
        return $this->plain($this->data['body'] ?? null);
    }

    public function iconName(): ?string
    {
        $icon = $this->data['icon'] ?? null;

        return is_string($icon) && $icon !== '' ? $icon : null;
    }

    public function iconColorName(): string
    {
        $color = $this->data['iconColor'] ?? $this->data['status'] ?? null;

        return is_string($color) && $color !== '' ? $color : 'gray';
    }

    /** Bildirimin goturdugu sayfa (ilk baglantili eylem); yoksa null. */
    public function targetUrl(): ?string
    {
        foreach ((array) ($this->data['actions'] ?? []) as $action) {
            $url = is_array($action) ? ($action['url'] ?? null) : null;

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function plain(mixed $value): string
    {
        return is_string($value) ? trim(html_entity_decode(strip_tags($value))) : '';
    }
}
