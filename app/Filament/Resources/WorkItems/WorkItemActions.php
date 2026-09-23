<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems;

use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Exceptions\AbstractException;
use App\Filament\Pages\Work\WorkBoard;
use App\Filament\Support\DomainNotifications;
use App\Models\Report\WorkItem;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Report\WorkItemQueries;
use App\Services\Report\WorkItemService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Kart detay ve duzenleme sayfalarinin baslik eylemleri (B36, D-115): tek
 * kayit islemleri etiketli baslik dugmesidir (durum degistir, kritik,
 * panoda ac, sil). Yazma isleri WorkItemService'ten gecer.
 */
final class WorkItemActions
{
    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label(__('work_item.actions.change_status'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('primary')
            ->visible(fn (WorkItem $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->fillForm(fn (WorkItem $record): array => [
                'status' => $record->status?->value,
                'waiting_kind' => $record->waiting_kind?->value,
                'waiting_personnel_id' => $record->waiting_personnel_id,
                'waiting_party_id' => $record->waiting_party_id,
                'waiting_text' => $record->waiting_text,
                'work_hours' => $record->work_hours,
            ])
            ->schema([
                Select::make('status')
                    ->label(__('work_item.fields.status'))
                    ->options(WorkItemStatus::class)
                    ->required()
                    ->live()
                    ->native(false),
                Select::make('waiting_kind')
                    ->label(__('work_item.fields.waiting_kind'))
                    ->options(WorkWaitingKind::class)
                    ->live()
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value)
                    ->required(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value),
                Select::make('waiting_personnel_id')
                    ->label(__('work_item.fields.waiting_personnel'))
                    ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value && self::value($get('waiting_kind')) === WorkWaitingKind::Personnel->value),
                Select::make('waiting_party_id')
                    ->label(__('work_item.fields.waiting_party'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(WorkItemQueries::class)->partyOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (app(WorkItemQueries::class)->partyOptions('', (int) $value)[(int) $value] ?? null) : null)
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value && self::value($get('waiting_kind')) === WorkWaitingKind::Party->value),
                TextInput::make('waiting_text')
                    ->label(__('work_item.fields.waiting_text'))
                    ->maxLength(200)
                    ->visible(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Waiting->value && in_array(self::value($get('waiting_kind')), [WorkWaitingKind::Text->value, WorkWaitingKind::Party->value], true)),
                TextInput::make('work_hours')
                    ->label(__('work_item.fields.work_hours'))
                    ->numeric()
                    ->step(0.25)
                    ->minValue(0)
                    ->maxValue(999)
                    ->visible(fn (Get $get): bool => self::value($get('status')) === WorkItemStatus::Done->value),
                Textarea::make('note')
                    ->label(__('work_item.fields.status_note'))
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (WorkItem $record, array $data): void {
                try {
                    app(WorkItemService::class)->changeStatus($record, self::value($data['status']), $data);
                    Notification::make()->success()->title(__('work_item.notifications.status_changed'))->send();
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function toggleCritical(): Action
    {
        return Action::make('toggleCritical')
            ->label(fn (WorkItem $record): string => $record->is_critical ? __('work_item.actions.unmark_critical') : __('work_item.actions.mark_critical'))
            ->icon(fn (WorkItem $record): Heroicon => $record->is_critical ? Heroicon::OutlinedMinusCircle : Heroicon::OutlinedExclamationTriangle)
            ->color(fn (WorkItem $record): string => $record->is_critical ? 'gray' : 'danger')
            ->visible(fn (WorkItem $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (WorkItem $record): void {
                try {
                    app(WorkItemService::class)->setCritical($record, ! $record->is_critical);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function openBoard(): Action
    {
        return Action::make('openBoard')
            ->label(__('work_item.actions.open_board'))
            ->icon(Heroicon::OutlinedViewColumns)
            ->color('gray')
            ->url(fn (): string => WorkBoard::getUrl());
    }

    public static function delete(): Action
    {
        return Action::make('deleteItem')
            ->label(__('work_item.actions.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('work_item.actions.delete_heading'))
            ->modalDescription(__('work_item.help.delete'))
            ->visible(fn (WorkItem $record): bool => auth()->user()?->can('delete', $record) ?? false)
            ->action(function (WorkItem $record, Action $action): void {
                try {
                    app(WorkItemService::class)->delete($record);
                    Notification::make()->success()->title(__('work_item.notifications.deleted'))->send();
                    $action->redirect(WorkItemResource::getUrl('index'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    private static function value(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
