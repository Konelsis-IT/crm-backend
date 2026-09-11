<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\Pages;

use App\Enums\Acquisition\ContractVersionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ContractVersions\ContractVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\ContractVersionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewContractVersion extends ViewRecord
{
    protected static string $resource = ContractVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make($this->statusActions())
                ->label(__('contract_version.actions.change_status'))
                ->icon(Heroicon::OutlinedArrowPath),
        ];
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (ContractVersionStatus::cases() as $target) {
            if (in_array($target, [ContractVersionStatus::Superseded], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('contract_version.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                    && $this->getRecord()->status->canTransitionTo($target))
                ->action(function (array $data) use ($target): void {
                    $record = $this->getRecord();
                    try {
                        app(ContractVersionService::class)->changeStatus($record, $target);
                        DomainNotifications::success(__('contract_version.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
