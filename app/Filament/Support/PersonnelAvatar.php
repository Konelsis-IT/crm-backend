<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Personnel\Personnel;
use Filament\Facades\Filament;

/**
 * Personel avatarinin adresi.
 *
 * Fotograf yuklenmisse fotograf, yuklenmemisse personelin adindan uretilen
 * bas harf avatari doner. Tek kaynak burasidir; tablo, detay ve kullanici
 * menusu ayni adresi kullanir.
 */
final class PersonnelAvatar
{
    public static function url(Personnel $record): string
    {
        return Filament::getUserAvatarUrl($record);
    }
}
