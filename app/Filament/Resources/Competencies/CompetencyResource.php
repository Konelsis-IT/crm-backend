<?php

declare(strict_types=1);

namespace App\Filament\Resources\Competencies;

use App\Enums\Personnel\CompetencyCategory;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\Competencies\Pages\ListCompetencies;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Competency;
use App\Services\Personnel\CompetencyService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CompetencyResource extends Resource
{
    protected static ?string $model = Competency::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('competency.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('competency.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('personnel.admin_ui')
            && SchemaReadiness::hasBatch('B02')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('competency.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('code')
                        ->label(__('competency.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name')
                        ->label(__('competency.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('category')
                        ->label(__('competency.fields.category'))
                        ->options(CompetencyCategory::class)
                        ->required()
                        ->native(false),
                    Select::make('status')
                        ->label(__('competency.fields.status'))
                        ->options(ActiveStatus::class)
                        ->default(ActiveStatus::Active->value)
                        ->required()
                        ->native(false),
                    Textarea::make('description')
                        ->label(__('competency.fields.description'))
                        ->rows(2)
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('competency.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('competency.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('competency.fields.category'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('personnel_count')
                    ->label(__('competency.fields.personnel_count'))
                    ->counts('personnel'),
                TextColumn::make('status')
                    ->label(__('competency.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('competency.fields.category'))
                    ->options(CompetencyCategory::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Competency $record, array $data): Model {
                        try {
                            return app(CompetencyService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompetencies::route('/'),
        ];
    }
}
