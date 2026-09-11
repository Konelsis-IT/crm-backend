<?php

declare(strict_types=1);

namespace App\Filament\Resources\Delegations\Pages;

use App\Enums\Approval\DelegationStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Delegations\DelegationResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Approval\Delegation;
use App\Services\Approval\DelegationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewDelegation extends ViewRecord
{
    protected static string $resource = DelegationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('delegation.sections.parties'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('grantor.full_name')->label(__('delegation.fields.grantor'))->icon(Heroicon::OutlinedUserCircle)->iconColor('primary'),
                    TextEntry::make('delegate.full_name')->label(__('delegation.fields.delegate'))->icon(Heroicon::OutlinedUserPlus)->iconColor('primary'),
                    TextEntry::make('status')->label(__('delegation.fields.status'))->badge(),
                    TextEntry::make('capability_code')
                        ->label(__('delegation.fields.capability_code'))
                        ->formatStateUsing(fn (string $state): string => $state === Delegation::CAPABILITY_APPROVAL_DECIDE ? __('delegation.capabilities.approval_decide') : $state)
                        ->badge()
                        ->color('info'),
                    TextEntry::make('scope_type')->label(__('delegation.fields.scope_type'))->badge()->color('gray'),
                    TextEntry::make('approver.full_name')->label(__('delegation.fields.approver'))->placeholder('-'),
                    TextEntry::make('valid_from')->label(__('delegation.fields.valid_from'))->dateTime('d.m.Y H:i')->icon(Heroicon::OutlinedCalendarDays)->iconColor('gray'),
                    TextEntry::make('valid_until')->label(__('delegation.fields.valid_until'))->dateTime('d.m.Y H:i')->icon(Heroicon::OutlinedCalendarDays)->iconColor('gray'),
                    TextEntry::make('reason')->label(__('delegation.fields.reason'))->columnSpanFull(),
                    TextEntry::make('revoked_at')->label(__('delegation.fields.revoked_at'))->dateTime('d.m.Y H:i')->placeholder('-')->visible(fn (Delegation $record): bool => $record->revoked_at !== null),
                    TextEntry::make('revoker.full_name')->label(__('delegation.fields.revoker'))->placeholder('-')->visible(fn (Delegation $record): bool => $record->revoked_at !== null),
                    TextEntry::make('revoke_reason')->label(__('delegation.fields.revoke_reason'))->placeholder('-')->visible(fn (Delegation $record): bool => $record->revoked_at !== null),
                ])),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => in_array($this->getRecord()->status, [DelegationStatus::Pending, DelegationStatus::Active], true)),
            Action::make('revoke')
                ->label(__('delegation.actions.revoke'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                    && in_array($this->getRecord()->status, [DelegationStatus::Pending, DelegationStatus::Active], true))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('delegation.fields.revoke_reason'))
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    try {
                        app(DelegationService::class)->revoke($this->getRecord(), $data['reason'] ?? null);
                        DomainNotifications::success(__('delegation.messages.revoked'));
                        $this->redirect(DelegationResource::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }
}
