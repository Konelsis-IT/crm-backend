<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Proposal;
use App\Services\Acquisition\ProposalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'proposals';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('proposal.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('proposal.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('proposal.fields.title'))
                            ->required()
                            ->maxLength(255),
                        Select::make('owner_employee_id')
                            ->label(__('proposal.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('proposal.label'))
            ->heading(__('proposal.relation.title'))
            ->recordTitleAttribute('proposal_no')
            ->columns([
                TextColumn::make('proposal_no')
                    ->label(__('proposal.fields.proposal_no')),
                TextColumn::make('title')
                    ->label(__('proposal.fields.title'))
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('proposal.fields.status'))
                    ->badge(),
                IconColumn::make('is_selected')
                    ->label(__('proposal.fields.is_selected'))
                    ->boolean(),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('proposal.fields.current_version'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['business_case_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProposalService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Proposal $record): string => ProposalResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (Proposal $record, array $data): Model {
                        try {
                            return app(ProposalService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('select')
                    ->label(__('proposal.actions.select'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Proposal $record): bool => ! $record->is_selected)
                    ->action(function (Proposal $record, array $data): void {
                        try {
                            app(\App\Services\Acquisition\ProposalService::class)->select($record);
                            DomainNotifications::success(__('proposal.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('proposal_no')
            ->emptyStateHeading(__('proposal.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }
}
