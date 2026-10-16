<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Query\Procurement\CatalogQueries;
use App\Support\Procurement\UnitResolver;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Sabit birim secimi (D-194, B54; kullanici: "Sistemde birimler sabit dropdown
 * menu olmalidir. Urun secildiginde urune ozel birim otomatik gelip alani
 * doldurmalidir").
 *
 * - make(): Ayarlar > Birimler'deki aktif birimler ("Metre (m)"), aranabilir,
 *   kisa alan (FieldGrid::SHORT). Kayitta secili pasif birim adiyla kalir.
 * - forItem(): satirin malzeme / hizmeti seciliyse ve onun birimi varsa alan
 *   o birimle kilitlenir (disabled + dehydrated, kilit simgesi); malzeme /
 *   hizmet yoksa ("Listede yok", serbest aciklama) birim listeden secilir.
 * - fillFromItem(): malzeme / hizmet secilince birimi alana yazar (B54
 *   oncesinde bos birim metnini kalemin metniyle doldurur - eski davranis).
 *
 * B54 uygulanmadan formlar eski serbest metin `unit` alanini kullanir
 * (enabled()); alan `uom_id` adini tasir ve servisler `unit` metnini birimin
 * adiyla esitler (UnitResolver::apply).
 */
final class UnitSelect
{
    public static function enabled(): bool
    {
        return UnitResolver::enabled();
    }

    public static function make(string $label, string $name = 'uom_id'): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (Select $component): array => app(UnitResolver::class)->options(FormState::value($component->getState())))
            ->getOptionLabelUsing(fn (mixed $value): ?string => app(UnitResolver::class)->label(FormState::value($value)))
            ->searchable()
            ->native(false)
            ->columnSpan(FieldGrid::SHORT);
    }

    /**
     * Malzeme / hizmete bagli birim: kalemin birimi varsa kilitli.
     *
     * @param  string|Closure(Get, ?Model): mixed  $item  Kardes alanin yolu (`catalog_item_id`) ya da kalem kimligini veren kapanis.
     * @param  (Closure(Get): bool)|null  $locked  Ek kilit (or. satin almanin isledigi kalem).
     */
    public static function forItem(string $label, string | Closure $item, ?Closure $locked = null, string $name = 'uom_id'): Select
    {
        $fromItem = static function (Get $get, ?Model $record) use ($item): bool {
            $itemId = is_string($item) ? FormState::value($get($item)) : $item($get, $record);

            return app(UnitResolver::class)->itemUnitId($itemId) !== null;
        };

        return self::make($label, $name)
            // D-194: talep, satin alinacak ve teklif kaleminde de birim zorunlu ("birimler sabit").
            ->required()
            ->disabled(fn (Get $get, Select $component): bool => ($locked !== null && $locked($get)) || $fromItem($get, $component->getRecord()))
            ->dehydrated()
            ->hintIcon(fn (Get $get, Select $component): ?Heroicon => $fromItem($get, $component->getRecord()) ? Heroicon::OutlinedLockClosed : null)
            ->hintIconTooltip(__('units.help.locked'));
    }

    /**
     * Malzeme / hizmet secilince birimi doldurur. B54'te kalemin birimi
     * (varsa) yazilir; oncesinde bos birim metni kalemin metnini alir.
     */
    public static function fillFromItem(mixed $itemId, Get $get, Set $set, string $name = 'uom_id', string $textField = 'unit'): void
    {
        $itemId = FormState::value($itemId);

        if (blank($itemId)) {
            return;
        }

        if (self::enabled()) {
            $unitId = app(UnitResolver::class)->itemUnitId($itemId);

            if ($unitId !== null) {
                $set($name, $unitId);
            }

            return;
        }

        if (blank($get($textField))) {
            $set($textField, app(CatalogQueries::class)->itemUnit($itemId));
        }
    }

    /**
     * Kalem durumunun gorunen birimi (kalem basligi rozeti).
     *
     * @param  array<string, mixed>  $state
     */
    public static function stateText(array $state): ?string
    {
        return app(UnitResolver::class)->stateText($state);
    }
}
