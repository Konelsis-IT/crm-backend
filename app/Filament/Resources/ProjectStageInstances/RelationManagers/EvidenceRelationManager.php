<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageEvidence;
use App\Services\Project\StageEvidenceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EvidenceRelationManager extends RelationManager
{
    protected static string $relationship = 'evidence';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPaperClip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stage_evidence.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_evidence.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('evidence_hash')
                            ->label(__('stage_evidence.fields.evidence_hash'))
                            ->maxLength(64),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('stage_evidence.relation.title'))
            ->recordTitleAttribute('evidence_hash')
            ->columns([
                TextColumn::make('requirement.requirement_code_snapshot')
                    ->label(__('stage_evidence.fields.requirement')),
                TextColumn::make('documentRevision.document.document_no')
                    ->label(__('stage_evidence.fields.document_revision')),
                TextColumn::make('documentRevision.title')
                    ->label(__('stage_evidence.fields.title'))
                    ->limit(30),
                TextColumn::make('submitter.full_name')
                    ->label(__('stage_evidence.fields.submitter')),
                TextColumn::make('submitted_at')
                    ->label(__('stage_evidence.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('acceptor.full_name')
                    ->label(__('stage_evidence.fields.acceptor'))
                    ->placeholder('-'),
                TextColumn::make('accepted_at')
                    ->label(__('stage_evidence.fields.accepted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
                Action::make('accept')
                    ->label(__('stage_evidence.actions.accept'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (StageEvidence $record): bool => $record->accepted_at === null)
                    ->action(function (StageEvidence $record, array $data): void {
                        try {
                            app(\App\Services\Project\StageEvidenceService::class)->accept($record);
                            DomainNotifications::success(__('stage_evidence.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('submitted_at', 'desc')
            ->emptyStateHeading(__('stage_evidence.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }
}
