<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\BusinessCases\Pages\CreateBusinessCase;
use App\Filament\Support\ActionColors;
use App\Models\Acquisition\BusinessCase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Taraf kartinda "Is Dosyalari" (D-129, 28 Eylul 2026 kullanici karari:
 * "Is dosyasiyla taraflar kismindaki baglantiyi da relation olarak sisteme
 * ekleyelim"). Tarafin musteri (primary_party) oldugu is dosyalari; tablo is
 * dosyasi listesiyle aynidir, satira tiklayinca is dosyasi acilir (D-125).
 * "Is dosyasi olustur" bu taraf secili olarak sihirbazi acar. Is dosyalari
 * ozelligi kapaliysa (acquisition.business_cases, D-128) sekme gorunmez.
 */
class BusinessCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessCases';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedBriefcase;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business_case.relation.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return BusinessCaseResource::canAccess();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $party = $this->getOwnerRecord();

        $table = BusinessCaseResource::table($table)
            ->heading(__('business_case.relation.title'))
            ->description(__('business_case.relation.party_help'))
            ->headerActions([
                Action::make('create_business_case')
                    ->label(__('business_case.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->color(ActionColors::CREATE)
                    ->visible(fn (): bool => auth()->user()?->can('create', BusinessCase::class) ?? false)
                    ->url(fn (): string => BusinessCaseResource::getUrl('create', [CreateBusinessCase::QUERY_PARTY => (int) $party->getKey()])),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('business_case.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBriefcase);

        // Taraf zaten bu kart; musteri sutunu tekrar gosterilmez.
        $table->getColumn('primaryParty.display_name')?->hidden();

        return $table;
    }
}
