<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Platform\Feature;
use App\Query\Acquisition\DraftQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Taslak kaydi (B43, D-155; 5 Ekim 2026 kullanici talimati: "Hem potansiyel
 * isler hem teklifler hem de ihaleler taslak durumunda kaydedilebilecektir.
 * Hangi adimlarda kaldiysa devam edilebilir olacaktir").
 *
 * Sihirbaz adimindaki "Taslak olarak kaydet" kaydi is_draft = 1 ve kaldigi
 * adimla (draft_step) yazar; normal "Kaydet" taslagi bitirir. Duzenleme kaldigi
 * adimdan acilir; listelerde "Taslaklar" sekmesi vardir. Kendi ozellik
 * anahtari (acquisition.drafts) kapaliyken dugmeler ve sekmeler gorunmez.
 */
final class DraftSupport
{
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B43') && FeatureFlags::enabled(Feature::AcquisitionDrafts);
    }

    /**
     * Kaydedilecek taslak alanlari: taslak kaydinda adim, normal kayitta
     * temizlenir; ozellik kapaliysa hic yazilmaz.
     *
     * @return array<string, mixed>
     */
    public static function attributes(bool $asDraft, string $step): array
    {
        if (! self::enabled()) {
            return [];
        }

        return $asDraft ? ['is_draft' => true, 'draft_step' => $step] : ['is_draft' => false, 'draft_step' => null];
    }

    public static function isDraft(Model $record): bool
    {
        return self::enabled() && (bool) $record->getAttribute('is_draft');
    }

    /**
     * Liste satirinda taslak isareti (D-162, 6 Ekim 2026 kullanici talimati:
     * "renklendirmeyle belirtmeliyiz, icon ile belirtmeliyiz; goren net
     * anlamali bu bir taslaktir diye"): amber serit ve zemin (kc-draft-row),
     * basligin onunde kalem simgesi, altinda amber "Taslak · ... adiminda kaldi".
     * Potansiyel is, teklif ve ihale listeleri ayni isareti kullanir.
     */
    public static function rowClass(Model $record): ?string
    {
        return self::isDraft($record) ? 'kc-draft-row' : null;
    }

    public static function titleIcon(Model $record): ?Heroicon
    {
        return self::isDraft($record) ? Heroicon::OutlinedPencilSquare : null;
    }

    public static function titleDescription(Model $record): ?string
    {
        return self::isDraft($record) ? self::label($record->getAttribute('draft_step')) : null;
    }

    public static function titleTooltip(Model $record): ?string
    {
        return self::isDraft($record) ? (string) __('app.values.draft_hint') : null;
    }

    /** "Taslak · Teklif adiminda kaldi" rozeti. */
    public static function label(mixed $step): string
    {
        $value = $step instanceof BackedEnum ? (string) $step->value : (string) $step;

        return $value === ''
            ? (string) __('app.values.draft')
            : (string) __('app.values.draft_at', ['step' => (string) __('business_case.wizard.'.$value)]);
    }

    /**
     * Liste sekmeleri: Tumu ve Taslaklar (sayiyla). Ozellik kapaliysa bos.
     *
     * @param  class-string<Model>  $model
     * @return array<string, Tab>
     */
    public static function tabs(string $model): array
    {
        if (! self::enabled()) {
            return [];
        }

        $queries = app(DraftQueries::class);

        return [
            'all' => Tab::make(__('app.tabs.all'))->badge($queries->total($model)),
            'drafts' => self::draftTab($model),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function draftTab(string $model): Tab
    {
        $queries = app(DraftQueries::class);

        // Taslak rengi her yerde amber (D-162).
        return Tab::make(__('app.tabs.drafts'))
            ->badge($queries->draftCount($model))
            ->badgeColor('warning')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modifyQueryUsing(fn (Builder $query): Builder => $queries->onlyDrafts($query));
    }
}
