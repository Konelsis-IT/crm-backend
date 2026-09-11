<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Enums\Document\DocumentLinkRole;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentLink;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentLinkService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokumanin baska kayitlara baglantilari (henuz kurulmamis modullerde
 * hedef secici yoktur; hedef turu/no elle girilir).
 */
class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedLink;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.relation.links.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document.relation.links.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('document_revision_id')
                        ->label(__('document_link.fields.revision'))
                        ->relationship(
                            'revision',
                            'revision_code',
                            modifyQueryUsing: fn ($query) => $query->where('document_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Select::make('link_role')
                        ->label(__('document_link.fields.link_role'))
                        ->options(DocumentLinkRole::class)
                        ->default(DocumentLinkRole::Reference->value)
                        ->required()
                        ->native(false),
                    TextInput::make('target_type')
                        ->label(__('document_link.fields.target_type'))
                        ->required()
                        ->maxLength(32),
                    TextInput::make('target_id')
                        ->label(__('document_link.fields.target_id'))
                        ->numeric()
                        ->required(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('document_link.label'))
            ->heading(__('document.relation.links.title'))
            ->recordTitleAttribute('target_type')
            ->columns([
                TextColumn::make('revision.revision_code')
                    ->label(__('document_link.fields.revision'))
                    ->placeholder('-'),
                TextColumn::make('target_type')
                    ->label(__('document_link.fields.target_type')),
                TextColumn::make('target_id')
                    ->label(__('document_link.fields.target_id')),
                TextColumn::make('link_role')
                    ->label(__('document_link.fields.link_role'))
                    ->badge(),
                TextColumn::make('linker.full_name')
                    ->label(__('document_link.fields.linker'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('app.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['document_id'] = $this->getOwnerRecord()->getKey();
                        $data['linked_by_personnel_id'] = app(ActorContext::class)->personnelId();

                        return app(DocumentLinkService::class)->create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (DocumentLink $record, array $data): Model => app(DocumentLinkService::class)->update($record, $data)),
                DeleteAction::make()
                    ->using(fn (DocumentLink $record): bool => app(DocumentLinkService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('document.relation.links.empty'))
            ->emptyStateIcon(Heroicon::OutlinedLink);
    }
}
