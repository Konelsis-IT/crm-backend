<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\OfferType;
use App\Filament\NavigationGroup;
use App\Filament\Resources\BusinessCases\Pages\CreateBusinessCase;
use App\Filament\Resources\BusinessCases\Pages\EditBusinessCase;
use App\Filament\Resources\BusinessCases\Pages\ListBusinessCases;
use App\Filament\Resources\BusinessCases\Pages\ViewBusinessCase;
use App\Filament\Resources\BusinessCases\RelationManagers\ContractsRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\MeetingNotesRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\OperationHandoffsRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\OpportunityRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\ProposalsRelationManager;
use App\Filament\Resources\Reports\RelationManagers\SubjectReportsRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\TenderNoticesRelationManager;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\ChecklistSchema;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\MoneyDisplay;
use App\Models\Acquisition\BusinessCase;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

class BusinessCaseResource extends Resource
{
    protected static ?string $model = BusinessCase::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('business_case.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business_case.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::BusinessCases)
            && SchemaReadiness::hasBatch('B16')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        // Olusturma ve duzenleme sayfalari (HasWizard) adimlari BusinessCaseWizard'dan
        // alir; alanlarin tek kaynagi BusinessCaseWizard::caseFields(), bolumler caseSections().
        return $schema->columns(1)->components([
            ...app(BusinessCaseWizard::class)->caseSections(),
        ]);
    }

    public static function table(Table $table): Table
    {
        // B29: kritiklik yerine teklif tipi ve proje kapsam rozetleri.
        $b29 = fn (): bool => SchemaReadiness::hasBatch('B29');
        $notB29 = fn (): bool => ! SchemaReadiness::hasBatch('B29');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['codes', 'primaryParty', 'owner', ...($b29() ? ['scopes'] : [])]))
            ->columns([
                // Kendi listesinde yalniz "Kod"; tekliflerde "Potansiyel iş kodu" (28 Eylul 2026 kullanici istegi).
                TextColumn::make('offer_code')
                    ->label(__('business_case.fields.code'))
                    ->getStateUsing(fn (BusinessCase $record): ?string => $record->caseCode()?->formatted_code)
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label(__('business_case.fields.title'))
                    ->limit(50)
                    // B43: taslagin kaldigi adim basligin altinda; D-162: kalem simgesi
                    // ve amber satir (taslak oldugu ilk bakista anlasilir).
                    ->description(fn (BusinessCase $record): ?string => DraftSupport::titleDescription($record))
                    ->icon(fn (BusinessCase $record): ?Heroicon => DraftSupport::titleIcon($record))
                    ->iconColor('warning')
                    ->tooltip(fn (BusinessCase $record): ?string => DraftSupport::titleTooltip($record))
                    ->searchable()
                    ->sortable(),
                // Teklif sicakligi (B43, D-155): kalp ve yuzde.
                TextColumn::make('heat_score')
                    ->label(__('checklist.heat'))
                    // D-167: bos sicaklik 0 sayilir ve %0 gorunur (0 = Yatirimci projesi).
                    ->default(0)
                    ->formatStateUsing(fn (mixed $state): HtmlString => ChecklistSchema::heatHtml(is_numeric($state) ? (int) $state : 0, small: true))
                    ->html()
                    ->sortable()
                    ->visible(fn (): bool => ChecklistSchema::enabled()),
                // Taraf tablosundaki ad sutunuyla ayni kisalik (28 Eylul 2026 kullanici istegi);
                // uzun adin tamami ipucunda.
                TextColumn::make('primaryParty.display_name')
                    ->label(__('business_case.fields.primary_party'))
                    ->limit(25)
                    ->tooltip(fn (BusinessCase $record): ?string => mb_strlen((string) $record->primaryParty?->display_name) > 25 ? $record->primaryParty?->display_name : null),
                TextColumn::make('acquisition_stage')
                    ->label(__('business_case.fields.acquisition_stage'))
                    ->badge(),
                // D-167: Is Gelistirme durumunda sicaklik 0 = Yatirimci projesi, > 0 = Potansiyel is.
                TextColumn::make('development_kind')
                    ->label(__('business_case.kind'))
                    ->state(fn (BusinessCase $record): ?string => $record->developmentKind())
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '-' : (string) __('business_case.kinds.'.$state))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'potential_job' ? 'amber' : 'slate')
                    ->placeholder('-'),
                TextColumn::make('outcome')
                    ->label(__('business_case.fields.outcome'))
                    ->badge(),
                TextColumn::make('criticality')
                    ->label(__('business_case.fields.criticality'))
                    ->badge()
                    ->visible($notB29),
                TextColumn::make('offer_type')
                    ->label(__('business_case.fields.offer_type'))
                    ->badge()
                    ->placeholder('-')
                    ->visible($b29),
                TextColumn::make('scopes.scope_type')
                    ->label(__('business_case.fields.scope_types'))
                    ->badge()
                    ->placeholder('-')
                    ->visible($b29),
                TextColumn::make('owner.full_name')
                    ->label(__('business_case.fields.owner'))
                    ->placeholder('-'),
                // D-180: tutar + para birimi simgesi (ISO kodu degil).
                MoneyDisplay::column('estimated_value')
                    ->label(__('business_case.fields.estimated_value'))
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('acquisition_stage')
                    ->label(__('business_case.fields.acquisition_stage'))
                    ->options(AcquisitionStage::class),
                SelectFilter::make('outcome')
                    ->label(__('business_case.fields.outcome'))
                    ->options(BusinessOutcome::class),
                SelectFilter::make('criticality')
                    ->label(__('business_case.fields.criticality'))
                    ->options(BusinessCriticality::class)
                    ->visible($notB29),
                SelectFilter::make('offer_type')
                    ->label(__('business_case.fields.offer_type'))
                    ->options(OfferType::class)
                    ->visible($b29),
            ])
            // Durum yalniz duzenleme sayfasindaki durum dugmesiyle degisir (D-161).
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->recordClasses(fn (BusinessCase $record): ?string => DraftSupport::rowClass($record))
            // Taslak satira tiklayinca kaldigi adimdan duzenleme acilir (B43).
            ->recordUrl(fn (BusinessCase $record): string => DraftSupport::enabled() && (bool) $record->getAttribute('is_draft') && Gate::allows('update', $record)
                ? self::getUrl('edit', ['record' => $record, 'step' => app(BusinessCaseWizard::class)->resumeStepId($record)])
                : self::getUrl('view', ['record' => $record]))
            ->defaultSort('sequence_no', 'desc');
    }

    public static function getRelations(): array
    {
        // Tekliflerin tam tablosu (secili yap, duzenle) ilk sekmede (D-143): detay
        // sayfasinda artik "Is akisi" sihirbazi yok; kisa ozetleri "Bu is nerede?"
        // hattinda. Duzenleme sihirbazi ayni tabloyu 2. adimda kullanmaya devam eder.
        // D-155 (5 Ekim 2026 kullanici karari): Aktiviteler sekmesi kaldirildi,
        // gorusme notlari yeterli. Kayitlar veritabaninda kalir.
        return [
            ProposalsRelationManager::class,
            // Gorusme notlari (B41, D-137): bu is ve teklifleri hakkindaki gorusmeler.
            MeetingNotesRelationManager::class,
            OpportunityRelationManager::class,
            TenderNoticesRelationManager::class,
            ContractsRelationManager::class,
            OperationHandoffsRelationManager::class,
            ...(SchemaReadiness::hasBatch('B10A') ? [SubjectReportsRelationManager::class] : []),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessCases::route('/'),
            'create' => CreateBusinessCase::route('/create'),
            'view' => ViewBusinessCase::route('/{record}'),
            'edit' => EditBusinessCase::route('/{record}/edit'),
        ];
    }
}
