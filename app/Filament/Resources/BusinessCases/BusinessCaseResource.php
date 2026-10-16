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
use App\Filament\Support\CodeLine;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\MoneyDisplay;
use App\Models\Acquisition\BusinessCase;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ChecklistTemplates;
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
                // Teklif sicakligi (B43, D-155): kalp ve yuzde. 15 Ekim 2026: basligin
                // solunda, sutun adi yok.
                TextColumn::make('heat_score')
                    ->label('')
                    // D-167: bos sicaklik 0 sayilir ve %0 gorunur (0 = Yatirimci projesi).
                    ->default(0)
                    ->formatStateUsing(fn (mixed $state): HtmlString => ChecklistSchema::heatHtml(is_numeric($state) ? (int) $state : 0, small: true))
                    ->html()
                    ->sortable()
                    ->visible(fn (): bool => ChecklistSchema::enabled()),
                // POTIS kodu ayri sutun degil, basligin altinda kucuk yazi (CodeLine,
                // 14 Ekim 2026); B43 taslak notu kodun yaninda; D-162: kalem simgesi
                // ve amber satir (taslak oldugu ilk bakista anlasilir).
                CodeLine::under(
                    TextColumn::make('title')
                        ->label(__('business_case.fields.title'))
                        ->limit(50)
                        ->icon(fn (BusinessCase $record): ?Heroicon => DraftSupport::titleIcon($record))
                        ->iconColor('warning')
                        ->tooltip(fn (BusinessCase $record): ?string => DraftSupport::titleTooltip($record))
                        ->searchable()
                        ->sortable(),
                    // POTIS yoksa (projeden acilan is) PRJ kodu.
                    fn (BusinessCase $record): ?string => ($record->caseCode() ?? $record->projectCode())?->formatted_code,
                    fn (BusinessCase $record): ?string => DraftSupport::titleDescription($record),
                ),
                // Taraf tablosundaki ad sutunuyla ayni kisalik (28 Eylul 2026 kullanici istegi);
                // uzun adin tamami ipucunda.
                TextColumn::make('primaryParty.display_name')
                    ->label(__('business_case.fields.primary_party'))
                    ->limit(25)
                    ->tooltip(fn (BusinessCase $record): ?string => mb_strlen((string) $record->primaryParty?->display_name) > 25 ? $record->primaryParty?->display_name : null),
                // 15 Ekim 2026: durum, tur ve sonuc tek sutunda. Rozet durum; altinda Is
                // Gelistirme durumunda tur (D-167: sicaklik 0 = Yatirimci projesi, > 0 =
                // Potansiyel is) ve acik olmayan sonuc (Kazanildi, Kaybedildi...).
                TextColumn::make('acquisition_stage')
                    ->label(__('business_case.fields.acquisition_stage'))
                    ->badge()
                    ->description(fn (BusinessCase $record): ?string => self::stageDetail($record)),
                TextColumn::make('criticality')
                    ->label(__('business_case.fields.criticality'))
                    ->badge()
                    ->visible($notB29),
                // 15 Ekim 2026: teklif tipi bos kalmaz; secilmemis kayitlar (ice aktarilan
                // isler) potansiyel isin varsayilan tipi Butcesel'dir (D-101).
                TextColumn::make('offer_type')
                    ->label(__('business_case.fields.offer_type'))
                    ->state(fn (BusinessCase $record): mixed => $record->getAttribute('offer_type') ?? OfferType::Budgetary)
                    ->badge()
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
            // Taslak amber (D-162); digerleri sicaklik rengiyle (15 Ekim 2026).
            ->recordClasses(fn (BusinessCase $record): ?string => DraftSupport::rowClass($record) ?? self::heatRowClass($record))
            // Taslak satira tiklayinca kaldigi adimdan duzenleme acilir (B43).
            ->recordUrl(fn (BusinessCase $record): string => DraftSupport::enabled() && (bool) $record->getAttribute('is_draft') && Gate::allows('update', $record)
                ? self::getUrl('edit', ['record' => $record, 'step' => app(BusinessCaseWizard::class)->resumeStepId($record)])
                : self::getUrl('view', ['record' => $record]))
            // 15 Ekim 2026: en sicak is en ustte (sonra en yeni).
            ->defaultSort(fn (Builder $query): Builder => app(BusinessCaseQueries::class)->hottestFirst($query));
    }

    /** Durum rozetinin alt satiri: Is Gelistirme'de tur, acik olmayan sonuc. */
    private static function stageDetail(BusinessCase $record): ?string
    {
        $stage = $record->getAttribute('acquisition_stage');
        $parts = [];

        if ($stage === AcquisitionStage::BusinessDevelopment && $record->developmentKind() !== null) {
            $parts[] = (string) __('business_case.kinds.'.$record->developmentKind());
        }

        $outcome = $record->getAttribute('outcome');

        if ($outcome instanceof BusinessOutcome && $outcome !== BusinessOutcome::Open && $outcome->getLabel() !== ($stage instanceof AcquisitionStage ? $stage->getLabel() : null)) {
            $parts[] = (string) $outcome->getLabel();
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Sicaklik rengi satira (yalniz sicakligi olan kayitlarda). */
    private static function heatRowClass(BusinessCase $record): ?string
    {
        $heat = $record->getAttribute('heat_score');

        if (! ChecklistSchema::enabled() || ! is_numeric($heat) || (int) $heat <= 0) {
            return null;
        }

        return 'kc-heat-row kc-heat-row--'.ChecklistTemplates::heatLevel((int) $heat);
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
