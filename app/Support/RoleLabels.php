<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rol adlarini ekran metnine cevirir: system_admin / auditor gibi teknik
 * adlar lang/role.php `names` dizisinden okunur; pozisyon rolleri (D-81)
 * zaten pozisyon basligini tasir ve oldugu gibi gosterilir.
 */
final class RoleLabels
{
    public static function name(string $role): string
    {
        $names = __('role.names');

        if (is_array($names) && isset($names[$role]) && is_string($names[$role])) {
            return $names[$role];
        }

        return $role;
    }

    /**
     * @param  iterable<int, string>  $roles
     */
    public static function list(iterable $roles): string
    {
        $labels = [];

        foreach ($roles as $role) {
            $labels[] = self::name((string) $role);
        }

        return implode(', ', $labels);
    }
}
