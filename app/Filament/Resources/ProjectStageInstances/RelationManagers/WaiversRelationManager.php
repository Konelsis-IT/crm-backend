<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageWaiver;
use App\Services\Project\StageWaiverService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WaiversRelationManager extends RelationManager
{
    protected static string $relationship = 'waivers';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedHandRaised;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stage_waiver.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_waiver.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('reason')
                            ->label(__('stage_waiver.fields.reason'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('stage_waiver.relation.title'))
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('requirement.requirement_code_snapshot')
                    ->label(__('stage_waiver.fields.requirement'))
                    ->placeholder('-'),
                TextColumn::make('reason')
                    ->label(__('stage_waiver.fields.reason'))
                    ->limit(40),
                TextColumn::make('approver.full_name')
                    ->label(__('stage_waiver.fields.approver')),
                TextColumn::make('riskOwner.full_name')
                    ->label(__('stage_waiver.fields.risk_owner')),
                TextColumn::make('remediation_due_on')
                    ->label(__('stage_waiver.fields.remediation_due_on'))
                    ->date('d.m.Y'),
                TextColumn::make('granted_at')
                    ->label(__('stage_waiver.fields.granted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
                
            ])
            ->toolbarActions([])
            ->defaultSort('granted_at', 'desc')
            ->emptyStateHeading(__('stage_waiver.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedHandRaised);
    }
}
