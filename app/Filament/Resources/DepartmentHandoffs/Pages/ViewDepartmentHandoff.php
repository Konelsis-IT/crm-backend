<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffs\Pages;

use App\Enums\Acquisition\HandoffStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\DepartmentHandoffs\DepartmentHandoffResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\DepartmentHandoffService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewDepartmentHandoff extends ViewRecord
{
    protected static string $resource = DepartmentHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make($this->statusActions())
                ->label(__('department_handoff.actions.change_status'))
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

        foreach (HandoffStatus::cases() as $target) {
            if (in_array($target, [HandoffStatus::Accepted], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('department_handoff.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('department_handoff.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                    && $this->getRecord()->status->canTransitionTo($target))
                ->action(function (array $data) use ($target): void {
                    $record = $this->getRecord();
                    try {
                        app(DepartmentHandoffService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('department_handoff.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
