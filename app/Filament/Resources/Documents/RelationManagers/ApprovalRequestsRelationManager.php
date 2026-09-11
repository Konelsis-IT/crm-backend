<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalRequestStep;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokumanin revizyonlari icin acilmis onay talepleri (B07, salt okunur).
 * Yeni talep sayfa ustundeki "Onaya gonder" ile acilir; karar Onaylar'da verilir.
 */
class ApprovalRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvalRequests';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCheckBadge;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('approval_request.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('approval_request.plural'))
            ->description(__('document.help.approval_tab'))
            ->columns([
                TextColumn::make('subject_label')
                    ->label(__('approval_request.fields.subject'))
                    ->wrap()
                    ->weight('semibold'),
                TextColumn::make('policyVersion.policy.code')
                    ->label(__('approval_request.fields.policy'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('requester.full_name')
                    ->label(__('approval_request.fields.requester'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('approval_request.fields.status'))
                    ->badge(),
                TextColumn::make('waiting_on')
                    ->label(__('approval_request.fields.waiting_on'))
                    ->getStateUsing(fn (ApprovalRequest $record): ?string => $record->activeSteps()->with('approver')->get()
                        ->map(fn (ApprovalRequestStep $row): string => $row->approver?->full_name ?? __('approval_request.values.unresolved'))
                        ->unique()
                        ->implode(', ') ?: null)
                    ->placeholder('-'),
                TextColumn::make('requested_at')
                    ->label(__('approval_request.fields.requested_at'))
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('decided_at')
                    ->label(__('approval_request.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label(__('approval_request.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (ApprovalRequest $record): string => ApprovalRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->defaultSort('approval_requests.requested_at', 'desc')
            ->emptyStateHeading(__('approval_request.messages.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge);
    }
}
