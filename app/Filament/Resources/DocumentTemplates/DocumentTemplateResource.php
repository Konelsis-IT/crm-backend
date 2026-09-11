<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentTemplates;

use App\Enums\Document\DocumentTemplateOutputKind;
use App\Enums\Document\DocumentTemplateStatus;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Filament\Resources\DocumentTemplates\Pages\ViewDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\RelationManagers\VersionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentTemplate;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('document_template.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('document_template.plural');
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
            Section::make(__('document_template.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('code')
                        ->label(__('document_template.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name')
                        ->label(__('document_template.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('output_kind')
                        ->label(__('document_template.fields.output_kind'))
                        ->options(DocumentTemplateOutputKind::class)
                        ->default(DocumentTemplateOutputKind::DocumentPdf->value)
                        ->required()
                        ->native(false),
                    Select::make('status')
                        ->label(__('document_template.fields.status'))
                        ->options(DocumentTemplateStatus::class)
                        ->default(DocumentTemplateStatus::Active->value)
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
                    ->label(__('document_template.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('document_template.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('output_kind')
                    ->label(__('document_template.fields.output_kind'))
                    ->badge(),
                TextColumn::make('versions_count')
                    ->label(__('document_template.relation.versions.title'))
                    ->counts('versions'),
                TextColumn::make('status')
                    ->label(__('document_template.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('output_kind')
                    ->label(__('document_template.fields.output_kind'))
                    ->options(DocumentTemplateOutputKind::class),
                SelectFilter::make('status')
                    ->label(__('document_template.fields.status'))
                    ->options(DocumentTemplateStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTemplates::route('/'),
            'create' => CreateDocumentTemplate::route('/create'),
            'view' => ViewDocumentTemplate::route('/{record}'),
            'edit' => EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
