<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentTypes;

use App\Enums\Document\DocumentDiscipline;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentType;
use App\Services\Document\DocumentTypeService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('document_type.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('document_type.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('documents.admin_ui')
            && SchemaReadiness::hasBatch('B06')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document_type.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('code')
                        ->label(__('document_type.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name')
                        ->label(__('document_type.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('discipline')
                        ->label(__('document_type.fields.discipline'))
                        ->options(DocumentDiscipline::class)
                        ->default(DocumentDiscipline::General->value)
                        ->required()
                        ->native(false),
                    TextInput::make('numbering_prefix')
                        ->label(__('document_type.fields.numbering_prefix'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32),
                    Select::make('default_classification_id')
                        ->label(__('document_type.fields.default_classification'))
                        ->relationship('defaultClassification', 'name_tr')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('default_retention_policy_id')
                        ->label(__('document_type.fields.default_retention_policy'))
                        ->relationship('defaultRetentionPolicy', 'name_tr')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    TagsInput::make('allowed_extensions')
                        ->label(__('document_type.fields.allowed_extensions'))
                        ->splitKeys([',', ' '])
                        ->columnSpanFull(),
                    TextInput::make('max_byte_size')
                        ->label(__('document_type.fields.max_byte_size'))
                        ->numeric()
                        ->minValue(1),
                    Toggle::make('is_controlled')
                        ->label(__('document_type.fields.is_controlled'))
                        ->default(true),
                    Select::make('status')
                        ->label(__('document_type.fields.status'))
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
                    ->label(__('document_type.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('document_type.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discipline')
                    ->label(__('document_type.fields.discipline'))
                    ->badge(),
                TextColumn::make('numbering_prefix')
                    ->label(__('document_type.fields.numbering_prefix')),
                IconColumn::make('is_controlled')
                    ->label(__('document_type.fields.is_controlled'))
                    ->boolean(),
                TextColumn::make('document_count')
                    ->label(__('document_type.fields.document_count'))
                    ->counts('documents'),
                TextColumn::make('status')
                    ->label(__('document_type.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('discipline')
                    ->label(__('document_type.fields.discipline'))
                    ->options(DocumentDiscipline::class),
                SelectFilter::make('status')
                    ->label(__('document_type.fields.status'))
                    ->options(ActiveStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (DocumentType $record, array $data): Model {
                        try {
                            return app(DocumentTypeService::class)->update($record, $data);
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
            'index' => ListDocumentTypes::route('/'),
        ];
    }
}
