<?php

declare(strict_types=1);

namespace App\Filament\Resources\FocusExpectations;

use App\Enums\Project\ExpectationKind;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\AbstractException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\FocusExpectations\Pages\ListFocusExpectations;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\FocusExpectation;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\FocusExpectationService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Odak beklentileri katalogu (Ayarlar): her adimin (operasyon grubunun)
 * projeden bekledigi veri ve sayim kurali. Calisma alani kontrol listesi ve
 * odak gecis guard'i bu tanimlari okur.
 */
class FocusExpectationResource extends Resource
{
    protected static ?string $model = FocusExpectation::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 111;

    protected static ?string $recordTitleAttribute = 'name_tr';

    public static function getModelLabel(): string
    {
        return __('focus_expectation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('focus_expectation.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('projects.admin_ui')
            && SchemaReadiness::hasBatch('B17A')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('focus_expectation.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('group_definition_id')
                        ->label(__('focus_expectation.fields.group'))
                        ->relationship('group', 'name_tr')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false)
                        ->native(false),
                    TextInput::make('code')
                        ->label(__('focus_expectation.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32),
                    TextInput::make('name_tr')
                        ->label(__('focus_expectation.fields.name_tr'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name_en')
                        ->label(__('focus_expectation.fields.name_en'))
                        ->required()
                        ->maxLength(255),
                    Select::make('kind')
                        ->label(__('focus_expectation.fields.kind'))
                        ->helperText(__('focus_expectation.help.kind'))
                        ->options(ExpectationKind::class)
                        ->required()
                        ->native(false),
                    TextInput::make('min_count')
                        ->label(__('focus_expectation.fields.min_count'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    Toggle::make('is_mandatory')
                        ->label(__('focus_expectation.fields.is_mandatory'))
                        ->helperText(__('focus_expectation.help.is_mandatory'))
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label(__('focus_expectation.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                    Textarea::make('help_tr')
                        ->label(__('focus_expectation.fields.help_tr'))
                        ->columnSpanFull(),
                    Textarea::make('help_en')
                        ->label(__('focus_expectation.fields.help_en'))
                        ->columnSpanFull(),
                    Select::make('status')
                        ->label(__('focus_expectation.fields.status'))
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
                TextColumn::make('group.name_tr')
                    ->label(__('focus_expectation.fields.group'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('focus_expectation.fields.code'))
                    ->searchable(),
                TextColumn::make('name_tr')
                    ->label(__('focus_expectation.fields.name_tr'))
                    ->searchable(),
                TextColumn::make('kind')
                    ->label(__('focus_expectation.fields.kind'))
                    ->badge(),
                TextColumn::make('min_count')
                    ->label(__('focus_expectation.fields.min_count')),
                IconColumn::make('is_mandatory')
                    ->label(__('focus_expectation.fields.is_mandatory'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('focus_expectation.fields.sort_order'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('focus_expectation.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('group_definition_id')
                    ->label(__('focus_expectation.fields.group'))
                    ->relationship('group', 'name_tr'),
                SelectFilter::make('kind')
                    ->label(__('focus_expectation.fields.kind'))
                    ->options(ExpectationKind::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (FocusExpectation $record, array $data): Model {
                        try {
                            return app(FocusExpectationService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('group_definition_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFocusExpectations::route('/'),
        ];
    }
}
