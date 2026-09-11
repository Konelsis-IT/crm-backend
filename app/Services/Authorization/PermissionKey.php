<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use Illuminate\Support\Str;

/**
 * Filament Shield izin anahtari uretimi (config/filament-shield.php:
 * `permissions.case = pascal`, `separator = ':'`, `resources.subject = model`).
 *
 * `App\Policies\DocumentPolicy` + `viewAny` -> `ViewAny:Document`
 * `App\Policies\PersonnelPolicy` + `changeStatus` -> `ChangeStatus:Personnel`
 * Ozel izinler (config `custom_permissions`): `notify:team` -> `Notify:Team`.
 */
final class PermissionKey
{
    public const SEPARATOR = ':';

    /** Politika sinifi + yetenek adi -> Shield anahtari. */
    public static function for(string $policyClass, string $ability): string
    {
        $subject = Str::beforeLast(class_basename($policyClass), 'Policy');

        return Str::studly($ability).self::SEPARATOR.Str::studly($subject);
    }

    /** Ozel izin anahtari (`notify:team` -> `Notify:Team`). */
    public static function custom(string $permission): string
    {
        return implode(self::SEPARATOR, array_map(
            static fn (string $segment): string => Str::studly($segment),
            explode(self::SEPARATOR, $permission),
        ));
    }
}
