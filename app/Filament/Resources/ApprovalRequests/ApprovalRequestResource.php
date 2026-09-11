<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalRequests;

use App\Enums\Approval\ApprovalRequestStatus;
use App\Filament\Resources\ApprovalRequests\Pages\ListApprovalRequests;
use App\Filament\Resources\ApprovalRequests\Pages\ViewApprovalRequest;
use App\Filament\Resources\ApprovalRequests\RelationManagers\DecisionsRelationManager;
use App\Filament\Resources\ApprovalRequests\RelationManagers\StepsRelationManager;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalRequestStep;
use App\Query\Approval\ApprovalQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Onaylar (onay kutusu): bana gelen kararlar, benim taleplerim, tum
 * talepler. Talep konu ekranindan acilir; burada karar verilir/izlenir.
 * Menude ust seviyede durur (tum departmanlari ilgilendirir); rozet karar
 * bekleyen adim sayisidir.
 */
class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = -1;

    protected static ?string $recordTitleAttribute = 'subject_label';

    public static function getModelLabel(): string
    {
        return __('approval_request.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('approval_request.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('approval_request.nav');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('approvals.admin_ui')
            && SchemaReadiness::hasBatch('B07')
            && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $personnelId = auth()->id();

        if ($personnelId === null || ! static::canAccess()) {
            return null;
        }

        $count = app(ApprovalQueries::class)->inboxCount((int) $personnelId);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_label')
                    ->label(__('approval_request.fields.subject'))
                    ->searchable()
                    ->wrap()
                    ->weight('semibold'),
                TextColumn::make('policyVersion.policy.code')
                    ->label(__('approval_request.fields.policy'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
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
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('requested_at')
                    ->label(__('approval_request.fields.requested_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('decided_at')
                    ->label(__('approval_request.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('approval_request.fields.status'))
                    ->options(ApprovalRequestStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->label(__('approval_request.actions.open')),
            ])
            ->toolbarActions([])
            ->defaultSort('requested_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
            DecisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequests::route('/'),
            'view' => ViewApprovalRequest::route('/{record}'),
        ];
    }
}
