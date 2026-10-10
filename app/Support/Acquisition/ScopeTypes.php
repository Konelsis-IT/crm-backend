<?php

declare(strict_types=1);

namespace App\Support\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;

/**
 * Secim listelerinde cikan proje tipleri (D-177, 8 Ekim 2026).
 *
 * "Otomasyon / Process" (`automation`) veritabani kisitlarina B50 ile girer ve
 * `acquisition.scope_automation` ozelligine baglidir (2.5). Grup uygulanmadan
 * ya da ozellik kapaliyken tip potansiyel is, teklif, proje, koordinator ve
 * firma faaliyet secimlerinde gorunmez; kayitli bir otomasyon satiri yine
 * adiyla ve simgesiyle gosterilir (gosterim ProjectScopeType'tan gelir).
 *
 * Secim alanlari secenekleri buradan alir, enum'u ayrica verir:
 * `->options(fn (): array => ScopeTypes::options())->enum(ProjectScopeType::class)`;
 * boylece simge ve renk enum'dan gelir, deger enum olarak okunur.
 */
final class ScopeTypes
{
    public static function automationEnabled(): bool
    {
        return SchemaReadiness::hasBatch('B50') && FeatureFlags::enabled(Feature::ScopeAutomation);
    }

    /**
     * Secilebilir tipler (katalog sirasi).
     *
     * @return list<ProjectScopeType>
     */
    public static function selectable(): array
    {
        $automation = self::automationEnabled();

        return array_values(array_filter(
            ProjectScopeType::cases(),
            static fn (ProjectScopeType $type): bool => $type !== ProjectScopeType::Automation || $automation,
        ));
    }

    /**
     * Secim alani secenekleri: deger => ad. $except verilen tipleri disarida birakir.
     *
     * @param  iterable<mixed>  $except
     * @return array<string, string>
     */
    public static function options(iterable $except = []): array
    {
        $skip = self::values($except);
        $options = [];

        foreach (self::selectable() as $type) {
            if (! in_array($type->value, $skip, true)) {
                $options[$type->value] = (string) $type->getLabel();
            }
        }

        return $options;
    }

    /**
     * Secenekler ve kayitli deger(ler): ozellik kapaliyken kayitta duran
     * Otomasyon secenekte kalir ki kayit dogrulamadan gecsin.
     *
     * @return array<string, string>
     */
    public static function optionsKeeping(mixed $current): array
    {
        $options = self::options();

        foreach (self::values(is_iterable($current) ? $current : [$current]) as $value) {
            $options[$value] ??= (string) ProjectScopeType::from($value)->getLabel();
        }

        return $options;
    }

    /**
     * Enum ya da metin listesini gecerli, tekil deger listesine indirger.
     *
     * @param  iterable<mixed>  $types
     * @return list<string>
     */
    public static function values(iterable $types): array
    {
        $values = [];

        foreach ($types as $type) {
            $value = $type instanceof BackedEnum ? (string) $type->value : trim((string) $type);

            if ($value === '' || ProjectScopeType::tryFrom($value) === null || in_array($value, $values, true)) {
                continue;
            }

            $values[] = $value;
        }

        return $values;
    }
}
