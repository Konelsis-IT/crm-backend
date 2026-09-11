<?php

declare(strict_types=1);

namespace App\Query\Authorization;

use App\Models\Authorization\Role;

/**
 * Rol ekranlarinin okuma sorgulari.
 */
final class RoleQueries
{
    /**
     * Rol secim listesi.
     *
     * @return array<int, string>
     */
    public function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
