<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Personnel\Personnel;

/** Personel tablosu (PersonnelTable) sutunlari; fotograf sutunu disa aktarilmaz. */
class PersonnelExporter extends KonelsisExporter
{
    protected static ?string $model = Personnel::class;

    public static function fileLabel(): string
    {
        return __('personnel.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('full_name', __('personnel.fields.full_name')),
            self::text('orgUnit.name', __('personnel.fields.department')),
            self::text('competencies.name', __('personnel.fields.competencies')),
            self::text('phone', __('personnel.fields.phone')),
            self::text('email', __('personnel.fields.email')),
            self::text('status', __('personnel.fields.status')),
            self::text('roles.name', __('role.fields.roles')),
            self::dateTime('last_login_at', __('personnel.fields.last_login_at')),
        ];
    }
}
