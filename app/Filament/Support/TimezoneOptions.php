<?php

declare(strict_types=1);

namespace App\Filament\Support;

use DateTimeZone;

final class TimezoneOptions
{
    /**
     * @return array<string, string>
     */
    public static function list(): array
    {
        $identifiers = DateTimeZone::listIdentifiers();

        return array_combine($identifiers, $identifiers);
    }
}
