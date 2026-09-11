<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Geo\TurkiyeRegions;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Il / ilce alanlari (D-80, 10 Eylul 2026 kullanici karari): Turkiye'de il ve
 * ilce gercek listeden secilir (TurkiyeRegions); ulke Turkiye degilse ya da
 * ulke alani olmayan formlarda "Türkiye dışında" isaretlenmisse elle yazilir.
 *
 * Ayni sutuna baglanan iki bilesen (secim / metin) birbirini dislayarak
 * gorunur; gorunmeyen kaydedilmez. Bilesen anahtarlari ayridir.
 */
final class TurkiyeAddressFields
{
    /**
     * @param  bool|Closure|null  $visible  Ek gorunurluk kosulu (ornegin sihirbazda "projeye cevir" secildiginde).
     * @return list<Component>
     */
    public static function make(
        string $cityField,
        string $districtField,
        string $cityLabel,
        string $districtLabel,
        ?string $countryField = null,
        bool $cityRequired = false,
        string $abroadField = 'address_abroad',
        bool | Closure | null $visible = null,
    ): array {
        $isAbroad = $countryField !== null
            ? fn (Get $get): bool => filled($get($countryField)) && strtoupper((string) $get($countryField)) !== TurkiyeRegions::COUNTRY_CODE
            : fn (Get $get): bool => (bool) $get($abroadField);

        $isShown = static function (Get $get) use ($visible): bool {
            if ($visible === null) {
                return true;
            }

            return (bool) ($visible instanceof Closure ? $visible($get) : $visible);
        };

        $components = [];

        if ($countryField === null) {
            $components[] = Toggle::make($abroadField)
                ->label(__('address.fields.abroad'))
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(function (Toggle $component, Get $get) use ($cityField): void {
                    $city = $get($cityField);

                    $component->state(filled($city) && ! TurkiyeRegions::isProvince((string) $city));
                })
                ->visible($isShown)
                ->columnSpan(FieldGrid::SHORT);
        }

        $components[] = Select::make($cityField)
            ->key($cityField.'_tr')
            ->label($cityLabel)
            ->options(TurkiyeRegions::provinceOptions())
            ->searchable()
            ->native(false)
            ->required($cityRequired)
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set($districtField, null))
            ->visible(fn (Get $get): bool => $isShown($get) && ! $isAbroad($get))
            ->columnSpan(FieldGrid::SHORT);

        $components[] = Select::make($districtField)
            ->key($districtField.'_tr')
            ->label($districtLabel)
            ->options(fn (Get $get): array => TurkiyeRegions::districtOptions($get($cityField)))
            ->searchable()
            ->native(false)
            ->disabled(fn (Get $get): bool => blank($get($cityField)))
            ->dehydrated(fn (Get $get): bool => filled($get($cityField)))
            ->visible(fn (Get $get): bool => $isShown($get) && ! $isAbroad($get))
            ->columnSpan(FieldGrid::SHORT);

        $components[] = TextInput::make($cityField)
            ->key($cityField.'_abroad')
            ->label($cityLabel)
            ->maxLength(100)
            ->required($cityRequired)
            ->visible(fn (Get $get): bool => $isShown($get) && $isAbroad($get))
            ->columnSpan(FieldGrid::SHORT);

        $components[] = TextInput::make($districtField)
            ->key($districtField.'_abroad')
            ->label($districtLabel)
            ->maxLength(100)
            ->visible(fn (Get $get): bool => $isShown($get) && $isAbroad($get))
            ->columnSpan(FieldGrid::SHORT);

        return $components;
    }
}
