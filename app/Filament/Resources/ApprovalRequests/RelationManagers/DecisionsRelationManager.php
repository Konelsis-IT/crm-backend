<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalRequests\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Models\Approval\ApprovalDecision;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Verilen kararlar (salt okunur): kim, hangi adimda, ne karar verdi;
 * vekaleten verildiyse kimin adina.
 */
class DecisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'decisions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('approval_request.relation.decisions.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('approval_request.relation.decisions.title'))
            ->columns([
                TextColumn::make('requestStep.step.name_tr')
                    ->label(__('approval_step.label'))
                    ->getStateUsing(fn (ApprovalDecision $record): string => $record->requestStep?->step?->localizedName() ?? '-'),
                TextColumn::make('decider.full_name')
                    ->label(__('approval_request.fields.decider')),
                TextColumn::make('onBehalfOf.full_name')
                    ->label(__('approval_request.fields.on_behalf_of'))
                    ->placeholder('-'),
                TextColumn::make('decision')
                    ->label(__('approval_request.fields.decision'))
                    ->badge(),
                TextColumn::make('comment')
                    ->label(__('approval_request.fields.comment'))
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('decided_at')
                    ->label(__('approval_request.fields.decided_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
            ])
            ->toolbarActions([])
            ->paginated(false)
            ->defaultSort('approval_decisions.decided_at', 'desc')
            ->emptyStateHeading(__('approval_request.relation.decisions.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }
}
