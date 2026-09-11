<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Personnel\Personnel;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Filament bildirim zili uzerinden teslimat (D-49: tek kanal zil). Bir
 * aliciya gonderim basarisiz olursa is akisi bozulmaz; gonderilen sayisi doner.
 */
final class PanelNotifier
{
    public function enabled(): bool
    {
        return FeatureFlags::enabled('notifications.database') && SchemaReadiness::hasBatch('B00');
    }

    /**
     * @param  iterable<Personnel>  $recipients
     * @param  list<Action>  $actions
     */
    public function send(iterable $recipients, string $title, ?string $body, Heroicon $icon, string $color, array $actions = []): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $sent = 0;

        foreach ($recipients as $personnel) {
            if (! $personnel instanceof Personnel) {
                continue;
            }

            try {
                $notification = Notification::make()
                    ->title($title)
                    ->icon($icon)
                    ->iconColor($color);

                if (filled($body)) {
                    $notification->body($body);
                }

                if ($actions !== []) {
                    $notification->actions($actions);
                }

                // Filament'in DatabaseNotification'i kuyruga girer (ShouldQueue); kuyruk
                // iscisi olmayan kurulumda zil hic dolmuyordu (11 Eylul 2026). Tek bir
                // satir ekleme oldugu icin aninda yazilir.
                $personnel->notifyNow($notification->toDatabase());
                $sent++;
            } catch (Throwable) {
                // Bildirim is akisini durdurmaz.
            }
        }

        return $sent;
    }
}
