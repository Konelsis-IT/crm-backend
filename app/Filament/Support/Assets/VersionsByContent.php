<?php

declare(strict_types=1);

namespace App\Filament\Support\Assets;

/**
 * Konelsis varliklarinin adres surumu dosya iceriginden uretilir (21 Eylul
 * 2026). Filament paket surumunu (?v=5.7.8.0) kullanir; bizim dosyalarimiz
 * degisse de adres degismedigi icin tarayici eski CSS / JS'i onbellekten
 * okuyordu (canlida talep yazismasi stilsiz gorundu). Yayimlanan dosyanin
 * (public/) ozetinin ilk 12 hanesi surum olur; dosya degisince adres de
 * degisir. Dosya henuz yayimlanmadiysa Filament'in surumu kullanilir.
 */
trait VersionsByContent
{
    /** @var array<string, string> istek boyunca hesaplanan ozetler */
    private static array $contentVersions = [];

    public function getVersion(): string
    {
        $path = $this->getPublicPath();

        if (! isset(self::$contentVersions[$path])) {
            $hash = is_file($path) ? md5_file($path) : false;
            self::$contentVersions[$path] = $hash === false ? parent::getVersion() : substr($hash, 0, 12);
        }

        return self::$contentVersions[$path];
    }
}
