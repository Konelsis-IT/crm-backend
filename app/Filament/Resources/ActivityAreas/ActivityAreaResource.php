<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityAreas;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\AbstractException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\ActivityAreas\Pages\ListActivityAreas;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\ActivityArea;
use App\Query\Party\ActivityAreaQueries;
use App\Services\Party\ActivityAreaService;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Faaliyet alanlari katalogu (B33, D-107): Ayarlar > Faaliyet alanlari.
 * Iki seviye: ana faaliyet alani (ust alan bos) ve alt faaliyet alani.
 * Taraf formundaki faaliyet satirlari ve taraf listesindeki suzgec bu
 * listeden beslenir. Kayit silinmez; kullanilmayan alan pasife alinir.
 *
 * Liste ana alan sirasiyla dizilir: her ana alan, hemen altinda kendi alt
 * alanlari.
 */
class ActivityAreaResource extends Resource
{
    protected static ?string $model = ActivityArea::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 85;

    protected static ?string $recordTitleAttribute = 'name_tr';

    public static function getModelLabel(): string
    {
        return __('activity_area.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('activity_area.plural');
    }

    public static function canAccess(): bool
    {
        return SchemaReadiness::hasBatch('B33') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('activity_area.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('parent_id')
                        ->label(__('activity_area.fields.parent'))
                        ->helperText(__('activity_area.help.parent'))
                        ->options(fn (?ActivityArea $record): array => array_diff_key(
                            app(ActivityAreaQueries::class)->rootOptions(includeInactive: true),
                            $record !== null ? [(int) $record->getKey() => true] : [],
                        ))
                        ->placeholder(__('activity_area.values.root'))
                        ->searchable()
                        ->native(false),
                    TextInput::make('code')
                        ->label(__('activity_area.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(64)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name_tr')
                        ->label(__('activity_area.fields.name_tr'))
                        ->required()
                        ->maxLength(150),
                    TextInput::make('name_en')
                        ->label(__('activity_area.fields.name_en'))
                        ->maxLength(150),
                    TextInput::make('sort_order')
                        ->label(__('activity_area.fields.sort_order'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(65535)
                        ->default(0),
                    Select::make('status')
                        ->label(__('activity_area.fields.status'))
                        ->options(ActiveStatus::class)
                        ->default(ActiveStatus::Active->value)
                        ->required()
                        ->native(false),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_tr')
                    ->label(__('activity_area.fields.name_tr'))
                    ->formatStateUsing(fn (ActivityArea $record, ?string $state): string => $record->isRoot() ? (string) $state : '— '.$state)
                    ->weight(fn (ActivityArea $record): ?string => $record->isRoot() ? 'bold' : null)
                    ->searchable(),
                TextColumn::make('parent.name_tr')
                    ->label(__('activity_area.fields.parent'))
                    ->placeholder(__('activity_area.values.root')),
                TextColumn::make('code')
                    ->label(__('activity_area.fields.code'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('name_en')
                    ->label(__('activity_area.fields.name_en'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->label(__('activity_area.fields.sort_order')),
                TextColumn::make('status')
                    ->label(__('activity_area.fields.status'))
                    ->badge(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => app(ActivityAreaQueries::class)->catalogOrder($query))
            ->filters([
                SelectFilter::make('parent_id')
                    ->label(__('activity_area.fields.parent'))
                    ->options(fn (): array => app(ActivityAreaQueries::class)->rootOptions(includeInactive: true))
                    ->query(fn (Builder $query, array $data): Builder => app(ActivityAreaQueries::class)
                        ->withinRoot($query, filled($data['value'] ?? null) ? (int) $data['value'] : null)),
                SelectFilter::make('status')
                    ->label(__('activity_area.fields.status'))
                    ->options(ActiveStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ActivityArea $record, array $data): Model {
                        try {
                            return app(ActivityAreaService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityAreas::route('/'),
        ];
    }
}
