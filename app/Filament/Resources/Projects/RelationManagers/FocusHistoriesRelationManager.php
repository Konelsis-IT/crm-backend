<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectFocusHistory;
use App\Services\Project\ProjectFocusHistoryService;
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

class FocusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'focusHistories';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedViewfinderCircle;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_focus_history.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_focus_history.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('reason')
                            ->label(__('project_focus_history.fields.reason'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('project_focus_history.relation.title'))
            ->recordTitleAttribute('direction')
            ->columns([
                TextColumn::make('workstream.group.name_tr')
                    ->label(__('project_focus_history.fields.workstream')),
                TextColumn::make('direction')
                    ->label(__('project_focus_history.fields.direction'))
                    ->badge(),
                TextColumn::make('started_at')
                    ->label(__('project_focus_history.fields.started_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label(__('project_focus_history.fields.ended_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('changer.full_name')
                    ->label(__('project_focus_history.fields.changer')),
                TextColumn::make('reason')
                    ->label(__('project_focus_history.fields.reason'))
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                
            ])
            ->toolbarActions([])
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading(__('project_focus_history.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedViewfinderCircle);
    }
}
