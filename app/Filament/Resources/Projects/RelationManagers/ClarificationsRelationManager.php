<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\ClarificationStatus;
use App\Enums\Project\ClarificationType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\CommercialClarification;
use App\Services\Project\CommercialClarificationService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ClarificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'clarifications';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('commercial_clarification.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('commercial_clarification.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('commercial_clarification.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('commercial_clarification.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('clarification_type')
                            ->label(__('commercial_clarification.fields.clarification_type'))
                            ->options(ClarificationType::class)
                            ->default(ClarificationType::ScopeInterpretation->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('commercial_clarification.fields.status'))
                            ->options(ClarificationStatus::class)
                            ->default(ClarificationStatus::Open->value)
                            ->required()
                            ->native(false),
                        Select::make('customer_contact_party_id')
                            ->label(__('commercial_clarification.fields.customer_contact_party'))
                            ->relationship('customerContact', 'display_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('linked_change_id')
                            ->label(__('commercial_clarification.fields.linked_change'))
                            ->relationship(
                            'linkedChange',
                            'change_no',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('response')
                            ->label(__('commercial_clarification.fields.response'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('commercial_clarification.label'))
            ->heading(__('commercial_clarification.relation.title'))
            ->recordTitleAttribute('clarification_no')
            ->columns([
                TextColumn::make('clarification_no')
                    ->label(__('commercial_clarification.fields.clarification_no')),
                TextColumn::make('title')
                    ->label(__('commercial_clarification.fields.title'))
                    ->limit(40),
                TextColumn::make('clarification_type')
                    ->label(__('commercial_clarification.fields.clarification_type'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('commercial_clarification.fields.status'))
                    ->badge(),
                TextColumn::make('responded_at')
                    ->label(__('commercial_clarification.fields.responded_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('linkedChange.change_no')
                    ->label(__('commercial_clarification.fields.linked_change'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(CommercialClarificationService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (CommercialClarification $record, array $data): Model {
                        try {
                            return app(CommercialClarificationService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('commercial_clarification.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }
}
