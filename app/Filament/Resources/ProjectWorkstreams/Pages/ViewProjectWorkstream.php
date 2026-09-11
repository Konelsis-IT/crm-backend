<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectWorkstreams\Pages;

use App\Enums\Project\WorkstreamStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ProjectWorkstreams\ProjectWorkstreamResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\ProjectWorkstreamService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewProjectWorkstream extends ViewRecord
{
    protected static string $resource = ProjectWorkstreamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make($this->statusActions())
                ->label(__('project_workstream.actions.change_status'))
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

        foreach (WorkstreamStatus::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('project_workstream.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('project_workstream.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                    && $this->getRecord()->status->canTransitionTo($target))
                ->action(function (array $data) use ($target): void {
                    $record = $this->getRecord();
                    try {
                        app(ProjectWorkstreamService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('project_workstream.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
