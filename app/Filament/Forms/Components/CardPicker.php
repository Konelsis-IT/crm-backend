<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use BackedEnum;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Icons\Heroicon;

/**
 * Kart secici (kullanici istegi, 23 Eylul 2026; ornek gorsel: "Choose how to
 * create your form"): secenekleri ikon + baslik + aciklamadan olusan kartlar
 * halinde gosterir, secilen kart isaretlenir.
 *
 * Filament'in kendi alanlari bu gorunumu vermiyor: `Select` acilir kutu,
 * `Radio` yalniz metin, `ToggleButtons` ise aciklamasiz kucuk dugmelerdir.
 * Bu yuzden kucuk bir Blade gorunumu (resources/views/filament/forms) ve
 * `.kc-picker*` stilleri (resources/css/filament/konelsis.css) kullanilir;
 * durum yine Filament alanidir (dogrulama, `live()`, `afterStateUpdated`).
 *
 * Kullanim:
 *   CardPicker::make('template_code')
 *       ->cards(fn (): array => [
 *           ['value' => 'daily', 'label' => 'Gunluk', 'description' => '...', 'icon' => Heroicon::OutlinedSun],
 *       ])
 */
class CardPicker extends Field
{
    protected string $view = 'filament.forms.card-picker';

    /** @var array<int, array<string, mixed>>|Closure */
    protected array | Closure $cards = [];

    /**
     * @param  array<int, array<string, mixed>>|Closure  $cards
     */
    public function cards(array | Closure $cards): static
    {
        $this->cards = $cards;

        return $this;
    }

    /**
     * Kartlar: value, label, description, icon (her zaman dolu).
     *
     * @return list<array{value: string, label: string, description: string|null, icon: string|BackedEnum}>
     */
    public function getCards(): array
    {
        $cards = $this->evaluate($this->cards);

        if (! is_array($cards)) {
            return [];
        }

        $normalized = [];

        foreach ($cards as $card) {
            if (! is_array($card) || ! array_key_exists('value', $card)) {
                continue;
            }

            $normalized[] = [
                'value' => (string) $card['value'],
                'label' => (string) ($card['label'] ?? $card['value']),
                'description' => filled($card['description'] ?? null) ? (string) $card['description'] : null,
                'icon' => $card['icon'] ?? Heroicon::OutlinedDocumentText,
            ];
        }

        return $normalized;
    }
}
