<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Actions;

use App\Enums\Personnel\PersonnelStatus;
use App\Filament\Support\DomainNotifications;
use App\Models\Personnel\Personnel;
use App\Services\Personnel\PersonnelService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

/**
 * Personel durumu formdan degil, yalniz bu islemlerle degisir (izinli
 * gecisler enumda). Ayni grup tablo satirinda simge, kayit sayfalarinda
 * (kart ve duzenleme) etiketli dugme olarak sunulur: islem yalniz tablo
 * satirinda dururken kullanici bulamiyordu (11 Eylul 2026).
 */
final class PersonnelStatusActions
{
    /** Tablo satiri: simge dugmesi, etiketi ipucu olarak. */
    public static function group(): ActionGroup
    {
        return ActionGroup::make(self::actions())
            ->label(__('personnel.actions.change_status'))
            ->tooltip(__('personnel.actions.change_status'))
            ->icon(Heroicon::OutlinedArrowPath);
    }

    /** Kayit sayfasi basligi: etiketli gri dugme. */
    public static function headerGroup(): ActionGroup
    {
        return self::group()
            ->button()
            ->color('gray');
    }

    /**
     * Her izin verilen hedef durum icin ayri islem.
     *
     * @return list<Action>
     */
    public static function actions(): array
    {
        $actions = [];

        foreach (PersonnelStatus::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('personnel.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('personnel.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (Personnel $record): bool => Gate::allows('changeStatus', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (Personnel $record, array $data, Component $livewire) use ($target): void {
                    try {
                        app(PersonnelService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        self::refresh($record, $livewire);
                        DomainNotifications::success(__('personnel.messages.status_changed'));
                    } catch (Throwable $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }

    /**
     * Servis kilitli taze bir ornek uzerinde calisir; sayfadaki kayit
     * tazelenmezse eski durum gorunur. Duzenleme formunda ayrica durum ve
     * satir surumu yenilenir; yoksa sonraki kayit "kayit degismis" verir.
     */
    private static function refresh(Personnel $record, Component $livewire): void
    {
        $record->refresh();

        if ($livewire instanceof EditRecord) {
            $livewire->refreshFormData(['status', 'row_version']);
        }
    }
}
