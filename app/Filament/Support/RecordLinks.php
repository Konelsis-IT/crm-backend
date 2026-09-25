<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Personnel\Personnel;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Kaydin detay sayfasi ve simgesi (tablo kurallari, 25 Eylul 2026 kullanici
 * karari D-125): satira tiklayinca detay acilir; tablodaki bagli kayda
 * (personel, teklif, is dosyasi...) tiklayinca onun detayi acilir ve
 * kaydin turunun simgesi gorunur. Personel adlarinin yaninda her zaman kisi
 * simgesi vardir.
 *
 * Bir modelin birden fazla resource'u olabilir (orn. Party: Taraflar ve
 * Dernekler); modelle ayni adli resource tercih edilir.
 */
final class RecordLinks
{
    public const PERSONNEL_ICON = Heroicon::OutlinedUserCircle;

    /** @var array<class-string<Model>, class-string<Resource>|null>|null */
    private static ?array $map = null;

    /** @var array<class-string<Resource>, bool> */
    private static array $access = [];

    /** @return class-string<Resource>|null */
    public static function resourceFor(Model | string $model): ?string
    {
        $class = $model instanceof Model ? $model::class : $model;

        if (self::$map === null) {
            self::$map = [];

            try {
                $resources = Filament::getCurrentPanel()?->getResources() ?? [];
            } catch (Throwable) {
                $resources = [];
            }

            foreach ($resources as $resource) {
                $modelClass = $resource::getModel();
                $preferred = class_basename($resource) === class_basename($modelClass).'Resource';

                if (! array_key_exists($modelClass, self::$map) || $preferred) {
                    self::$map[$modelClass] = $resource;
                }
            }
        }

        return self::$map[$class] ?? null;
    }

    /**
     * Kaydin detay sayfasi: goruntuleme sayfasi, yoksa duzenleme sayfasi.
     * Satir icin kayit duzeyinde yetki bakilir (Filament'in varsayilani);
     * bagli kayit baglantisinda yalniz resource erisimine bakilir, detay
     * sayfasi yetkiyi ayrica denetler (D-118 ilkesi).
     *
     * @param  class-string<Resource>|null  $resource
     */
    public static function detailUrl(Model $record, ?string $resource = null, bool $checkRecord = true): ?string
    {
        $resource ??= self::resourceFor($record);

        if ($resource === null || ! self::canAccess($resource)) {
            return null;
        }

        try {
            foreach (['view' => 'canView', 'edit' => 'canEdit'] as $page => $ability) {
                if (! $resource::hasPage($page)) {
                    continue;
                }

                if ($checkRecord && ! $resource::{$ability}($record)) {
                    continue;
                }

                return $resource::getUrl($page, ['record' => $record]);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Alan adindaki iliski zincirinin son kaydi ("personnel.full_name" ->
     * personel). Tekil iliski degilse (liste, bos) null.
     */
    public static function related(Model $record, string $path): ?Model
    {
        $segments = explode('.', $path);
        array_pop($segments);

        if ($segments === []) {
            return null;
        }

        $current = $record;

        foreach ($segments as $segment) {
            if ($current->hasAttribute($segment) || ! $current->isRelation($segment)) {
                return null;
            }

            try {
                $current = $current->getRelationValue($segment);
            } catch (Throwable) {
                return null;
            }

            if (! $current instanceof Model) {
                return null;
            }
        }

        return $current === $record ? null : $current;
    }

    /** Kaydin turunun simgesi: personel icin kisi simgesi, digerleri resource simgesi. */
    public static function iconFor(Model $record): string | BackedEnum | Htmlable | null
    {
        if ($record instanceof Personnel) {
            return self::PERSONNEL_ICON;
        }

        $resource = self::resourceFor($record);

        if ($resource === null) {
            return null;
        }

        try {
            return $resource::getNavigationIcon();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  class-string<Resource>  $resource */
    private static function canAccess(string $resource): bool
    {
        if (! array_key_exists($resource, self::$access)) {
            try {
                self::$access[$resource] = $resource::canAccess();
            } catch (Throwable) {
                self::$access[$resource] = false;
            }
        }

        return self::$access[$resource];
    }
}
