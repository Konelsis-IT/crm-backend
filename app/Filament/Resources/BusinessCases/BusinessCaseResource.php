<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\OfferType;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\BusinessCases\Pages\CreateBusinessCase;
use App\Filament\Resources\BusinessCases\Pages\EditBusinessCase;
use App\Filament\Resources\BusinessCases\Pages\ListBusinessCases;
use App\Filament\Resources\BusinessCases\Pages\ViewBusinessCase;
use App\Filament\Resources\BusinessCases\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\ContractsRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\OperationHandoffsRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\OpportunityRelationManager;
use App\Filament\Resources\Reports\RelationManagers\SubjectReportsRelationManager;
use App\Filament\Resources\BusinessCases\RelationManagers\TenderNoticesRelationManager;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\BusinessCase;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
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
        return FeatureFlags::enabled('acquisition.admin_ui')
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
                TextColumn::make('offer_code')
                    ->label(__('business_case.fields.offer_code'))
                    ->getStateUsing(fn (BusinessCase $record): ?string => $record->offerCode()?->formatted_code)
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label(__('business_case.fields.title'))
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primaryParty.display_name')
                    ->label(__('business_case.fields.primary_party')),
                TextColumn::make('acquisition_stage')
                    ->label(__('business_case.fields.acquisition_stage'))
                    ->badge(),
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
                TextColumn::make('estimated_value')
                    ->label(__('business_case.fields.estimated_value'))
                    ->numeric(decimalPlaces: 2)
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(self::statusActions())
                    ->label(__('business_case.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('sequence_no', 'desc');
    }

    public static function getRelations(): array
    {
        // Teklifler zincir sihirbazinin 2. adimindadir (BusinessCaseWizard::proposalTableStep).
        return [
            OpportunityRelationManager::class,
            ActivitiesRelationManager::class,
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

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private static function statusActions(): array
    {
        $actions = [];

        foreach (AcquisitionStage::cases() as $target) {
            if (in_array($target, [AcquisitionStage::HandoverAccepted], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('business_case.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('business_case.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (BusinessCase $record): bool => Gate::allows('update', $record)
                    && $record->acquisition_stage->canTransitionTo($target))
                ->action(function (BusinessCase $record, array $data) use ($target): void {
                    try {
                        app(BusinessCaseService::class)->changeStage($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('business_case.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
