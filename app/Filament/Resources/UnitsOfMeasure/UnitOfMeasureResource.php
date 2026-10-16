<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnitsOfMeasure;

use App\Enums\Platform\Feature;
use App\Enums\Reference\UomDimension;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\AbstractException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\UnitsOfMeasure\Pages\ListUnitsOfMeasure;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Reference\UnitOfMeasure;
use App\Query\Reference\UnitQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Reference\UnitOfMeasureService;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Ayarlar > Birimler (D-194, B54; kullanici 12 Ekim 2026: "birimler sabit
 * olacak. Istersen onu da ayarlara ekleyelim. Sistemde birimler sabit
 * dropdown menu olmalidir").
 *
 * Liste ture gore gruplanir (Ad, Simge, Tur, Durum). Ekleme ve duzenleme
 * pencerede: yalniz Ad, Simge ve Tur; kod simgeden uretilir, Ingilizce ad
 * yoktur (D-130, D-131; UnitOfMeasureService). Silme yok: "Pasife al" birimi
 * secim listelerinden kaldirir, var olan kayitlar onu gostermeye devam eder.
 * Ekran B54 ve "Satin alma: Birimler" ozelligiyle acilir; ozellik kapaliyken
 * satin alma formlarindaki birim secimi calismaya devam eder.
 */
class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'units';

    protected static ?string $recordTitleAttribute = 'name_tr';

    public static function getModelLabel(): string
    {
        return __('units.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('units.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('units.nav');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::Units) && UnitResolver::enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(FieldGrid::MODAL_COLUMNS)
            ->components(FieldGrid::modal([
                TextInput::make('name_tr')
                    ->label(__('units.fields.name'))
                    ->helperText(__('units.help.name'))
                    ->required()
                    ->maxLength(UnitOfMeasureService::NAME_LENGTH)
                    ->columnSpan(['default' => 1, 'md' => 2]),
                TextInput::make('symbol')
                    ->label(__('units.fields.symbol'))
                    ->helperText(__('units.help.symbol'))
                    ->required()
                    ->maxLength(UnitOfMeasureService::SYMBOL_LENGTH)
                    ->columnSpan(['default' => 1, 'md' => 1]),
                Select::make('dimension')
                    ->label(__('units.fields.dimension'))
                    ->options(UomDimension::class)
                    ->default(UomDimension::Count->value)
                    ->required()
                    ->native(false)
                    ->columnSpan(['default' => 1, 'md' => 2])
                    ->columnStart(1),
                Hidden::make('row_version')->hiddenOn('create'),
            ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(UnitQueries::class)->listOrder($query))
            ->defaultGroup(
                Group::make('dimension')
                    ->label(__('units.fields.dimension'))
                    ->getTitleFromRecordUsing(fn (UnitOfMeasure $record): string => (string) $record->dimensionEnum()->getLabel())
                    ->collapsible(),
            )
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('name_tr')
                    ->label(__('units.fields.name'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('symbol')
                    ->label(__('units.fields.symbol'))
                    ->searchable(),
                TextColumn::make('dimension')
                    ->label(__('units.fields.dimension'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('units.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('dimension')
                    ->label(__('units.filters.dimension'))
                    ->options(UomDimension::class)
                    ->native(false),
                SelectFilter::make('status')
                    ->label(__('units.filters.status'))
                    ->options(ActiveStatus::class)
                    ->native(false),
            ])
            // Satira tiklamak duzenleme penceresini acar (ayri detay sayfasi yok).
            ->recordAction(fn (UnitOfMeasure $record): ?string => Gate::allows('update', $record) ? 'edit' : null)
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->using(function (UnitOfMeasure $record, array $data): Model {
                        try {
                            return app(UnitOfMeasureService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                self::statusAction(deactivate: true),
                self::statusAction(deactivate: false),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('units.empty'))
            ->emptyStateIcon(Heroicon::OutlinedScale)
            ->paginated([50, 100])
            ->defaultPaginationPageOption(100);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitsOfMeasure::route('/'),
        ];
    }

    /** "Pasife al" / "Aktif yap": duz onay, silme yok. */
    private static function statusAction(bool $deactivate): Action
    {
        return Action::make($deactivate ? 'deactivate_unit' : 'activate_unit')
            ->label(__($deactivate ? 'units.actions.deactivate' : 'units.actions.activate'))
            ->icon($deactivate ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color($deactivate ? ActionColors::CANCEL : ActionColors::SAVE)
            ->requiresConfirmation()
            ->modalHeading(__($deactivate ? 'units.actions.deactivate' : 'units.actions.activate'))
            ->modalDescription(__($deactivate ? 'units.help.deactivate' : 'units.help.activate'))
            ->modalSubmitActionLabel(__($deactivate ? 'units.actions.deactivate' : 'units.actions.activate'))
            ->visible(fn (UnitOfMeasure $record): bool => $record->isActive() === $deactivate && Gate::allows('update', $record))
            ->action(function (UnitOfMeasure $record) use ($deactivate): void {
                try {
                    $service = app(UnitOfMeasureService::class);
                    $deactivate ? $service->deactivate($record) : $service->activate($record);
                    DomainNotifications::success(__($deactivate ? 'units.messages.deactivated' : 'units.messages.activated'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $record->refresh();
            });
    }
}
