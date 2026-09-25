<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalRequests\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Models\Approval\ApprovalRequestStep;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Talebin adimlari ve her adimdaki onayci satirlari (salt okunur).
 */
class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedQueueList;

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('approval_request.relation.steps.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('approval_request.relation.steps.title'))
            ->columns([
                TextColumn::make('sequence_no')
                    ->label(__('approval_step.fields.sequence_no'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('step.name_tr')
                    ->label(__('approval_step.label'))
                    ->getStateUsing(fn (ApprovalRequestStep $record): string => $record->step?->localizedName() ?? '-')
                    ->weight('semibold'),
                TextColumn::make('approver.full_name')
                    ->label(__('approval_request.fields.approver'))
                    ->placeholder(__('approval_request.values.unresolved')),
                TextColumn::make('resolved_role_snapshot')
                    ->label(__('approval_step.fields.resolver_type'))
                    ->formatStateUsing(fn (string $state): string => __('enums.resolver_type.'.$state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('unresolved_reason')
                    ->label(__('approval_request.fields.unresolved_reason'))
                    ->badge()
                    ->color('danger')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('approval_request.fields.step_status'))
                    ->badge(),
                TextColumn::make('activated_at')
                    ->label(__('approval_request.fields.activated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('due_at')
                    ->label(__('approval_request.fields.due_at'))
                    ->dateTime('d.m.Y H:i')
                    ->color(fn (ApprovalRequestStep $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->placeholder('-'),
                TextColumn::make('decided_at')
                    ->label(__('approval_request.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
            ])
            ->toolbarActions([])
            ->paginated(false)
            ->defaultSort('sequence_no');
    }
}
