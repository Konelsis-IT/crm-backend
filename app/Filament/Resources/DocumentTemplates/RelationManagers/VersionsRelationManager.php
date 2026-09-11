<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentTemplates\RelationManagers;

use App\Enums\Document\TemplateVersionStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentTemplateVersion;
use App\Services\Document\DocumentTemplateVersionService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Sablonun dile ozel surumleri; version_no otomatik uretilir (DocumentTemplateVersionService).
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentText;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document_template.relation.versions.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document_template.relation.versions.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('locale')
                        ->label(__('document_template.fields.locale'))
                        ->options(['tr' => 'Türkçe', 'en' => 'English'])
                        ->required()
                        ->native(false),
                    TextInput::make('view_key')
                        ->label(__('document_template.fields.view_key'))
                        ->required()
                        ->maxLength(128)
                        ->columnSpanFull(),
                    Select::make('status')
                        ->label(__('document_template.fields.version_status'))
                        ->options(TemplateVersionStatus::class)
                        ->default(TemplateVersionStatus::Draft->value)
                        ->required()
                        ->native(false),
                    KeyValue::make('layout_config')
                        ->label(__('document_template.fields.layout_config'))
                        ->columnSpanFull(),
                    TagsInput::make('required_field_keys')
                        ->label(__('document_template.fields.required_field_keys'))
                        ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('document_template.label'))
            ->heading(__('document_template.relation.versions.title'))
            ->recordTitleAttribute('view_key')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('document_template.fields.version_no'))
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('document_template.fields.locale'))
                    ->badge(),
                TextColumn::make('view_key')
                    ->label(__('document_template.fields.view_key'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('document_template.fields.version_status'))
                    ->badge(),
                TextColumn::make('published_at')
                    ->label(__('document_template.fields.published_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('publisher.full_name')
                    ->label(__('document_template.fields.publisher'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['document_template_id'] = $this->getOwnerRecord()->getKey();

                        return app(DocumentTemplateVersionService::class)->create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (DocumentTemplateVersion $record, array $data): Model {
                        try {
                            return app(DocumentTemplateVersionService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(fn (DocumentTemplateVersion $record): bool => app(DocumentTemplateVersionService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('document_template.relation.versions.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }
}
