<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Shared label resolution for backed enums shown in Filament.
 * Translation key: enums.<snake_case_enum_name>.<value>
 */
trait HasTranslatedLabel
{
    public function getLabel(): string
    {
        $key = 'enums.'.Str::snake(class_basename(static::class)).'.'.$this->value;
        $translated = __($key);

        return $translated === $key ? Str::headline($this->value) : $translated;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
