<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderSources;

use App\Enums\Acquisition\TenderAccessMode;
use App\Enums\Acquisition\TenderSourceType;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\TenderSources\Pages\ListTenderSources;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderSource;
use App\Services\Acquisition\TenderSourceService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TenderSourceResource extends Resource
{
    protected static ?string $model = TenderSource::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'name_tr';

    public static function getModelLabel(): string
    {
        return __('tender_source.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tender_source.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('acquisition.admin_ui')
            && SchemaReadiness::hasBatch('B16')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('tender_source.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('code')
                            ->label(__('tender_source.fields.code'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(64)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        TextInput::make('name_tr')
                            ->label(__('tender_source.fields.name_tr'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('tender_source.fields.name_en'))
                            ->required()
                            ->maxLength(255),
                        Select::make('source_type')
                            ->label(__('tender_source.fields.source_type'))
                            ->options(TenderSourceType::class)
                            ->default(TenderSourceType::PublicProcurement->value)
                            ->required()
                            ->native(false),
                        Select::make('access_mode')
                            ->label(__('tender_source.fields.access_mode'))
                            ->options(TenderAccessMode::class)
                            ->default(TenderAccessMode::Manual->value)
                            ->required()
                            ->native(false),
                        TextInput::make('base_url')
                            ->label(__('tender_source.fields.base_url'))
                            ->maxLength(2048),
                        TextInput::make('terms_reference')
                            ->label(__('tender_source.fields.terms_reference'))
                            ->maxLength(100),
                        Toggle::make('scraping_allowed')
                            ->label(__('tender_source.fields.scraping_allowed')),
                        Select::make('status')
                            ->label(__('tender_source.fields.status'))
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
                TextColumn::make('code')
                    ->label(__('tender_source.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_tr')
                    ->label(__('tender_source.fields.name_tr'))
                    ->searchable(),
                TextColumn::make('source_type')
                    ->label(__('tender_source.fields.source_type'))
                    ->badge(),
                TextColumn::make('access_mode')
                    ->label(__('tender_source.fields.access_mode'))
                    ->badge(),
                IconColumn::make('scraping_allowed')
                    ->label(__('tender_source.fields.scraping_allowed'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('tender_source.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (TenderSource $record, array $data): Model {
                        try {
                            return app(TenderSourceService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenderSources::route('/'),
        ];
    }
}
