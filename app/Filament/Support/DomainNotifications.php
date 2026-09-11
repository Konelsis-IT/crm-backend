<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Exceptions\AbstractException;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Servis katmanindan gelen is hatalarini Filament bildirimine cevirir.
 *
 * Mesaj artik burada yazilmaz: AbstractException'dan turemis her hata
 * kendi dil karsiligini tasir. Bilinmeyen hatalarda genel metin gosterilir.
 */
final class DomainNotifications
{
    public static function failure(Throwable $exception): void
    {
        $body = $exception instanceof AbstractException
            ? $exception->userMessage()
            : __('exceptions.generic');

        Notification::make()
            ->title(__('app.errors.title'))
            ->body($body)
            ->danger()
            ->send();
    }

    public static function success(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }
}
