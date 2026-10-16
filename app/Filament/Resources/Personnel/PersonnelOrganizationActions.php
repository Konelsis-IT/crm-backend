<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel;

use App\Filament\Resources\Delegations\DelegationResource;
use App\Filament\Resources\OrgUnits\OrgUnitResource;
use App\Filament\Resources\Positions\PositionResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\RecordHeading;
use App\Filament\Support\RecordLinks;
use App\Models\Approval\Delegation;
use App\Query\Approval\ApprovalQueries;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Personel listesinin baslik dugmeleri (D-206; kullanici 14 Ekim 2026:
 * "Personel listesinde action buton olarak organizasyon birimleri -
 * departmanlar - pozisyonlar - vekaletler hepsi action olarak eklenip sol
 * menuden kaldirilsin ... bazilari modal bazilari direk listesini ... acabilir").
 *
 * - Organizasyon birimleri ve Pozisyonlar: kendi listesine gider (detay
 *   sayfalari ve personel sekmeleri olan kayitlar).
 * - Vekaletler: pencerede gecerli ve baslayacak vekaletler; "Tum vekaletler"
 *   listeye, "Vekalet ver" olusturma sayfasina gider.
 *
 * Her dugme hedef ekranin kendi erisim kuralina (ozellik + yetki) gore gorunur.
 */
final class PersonnelOrganizationActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::link('orgUnits', OrgUnitResource::class),
            self::link('positions', PositionResource::class),
            self::delegations(),
        ];
    }

    /**
     * @param  class-string<resource>  $resource
     */
    private static function link(string $name, string $resource): Action
    {
        return Action::make($name)
            ->label(RecordHeading::sentenceCase((string) $resource::getPluralModelLabel()))
            ->icon($resource::getNavigationIcon())
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (): bool => $resource::canAccess())
            ->url(fn (): string => $resource::getUrl('index'));
    }

    private static function delegations(): Action
    {
        return Action::make('delegations')
            ->label(RecordHeading::sentenceCase((string) DelegationResource::getPluralModelLabel()))
            ->icon(DelegationResource::getNavigationIcon())
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (): bool => DelegationResource::canAccess())
            ->modalHeading(RecordHeading::sentenceCase((string) DelegationResource::getPluralModelLabel()))
            ->modalDescription(__('delegation.help.open_list'))
            ->modalIcon(DelegationResource::getNavigationIcon())
            ->modalWidth(Width::FourExtraLarge)
            ->schema([
                RepeatableEntry::make('open_delegations')
                    ->label(__('delegation.plural'))
                    ->hiddenLabel()
                    // Yalniz gorme yetkisi olan vekaletler (DelegationPolicy::view: taraflar ve yoneticiler).
                    ->state(fn (): array => app(ApprovalQueries::class)->openDelegations()
                        ->filter(fn (Delegation $delegation): bool => Gate::allows('view', $delegation))
                        ->values()
                        ->map(fn (Delegation $delegation): array => [
                            'grantor' => $delegation->grantor?->full_name,
                            'delegate' => $delegation->delegate?->full_name,
                            'scope' => $delegation->scope_type,
                            'period' => self::period($delegation),
                            'status' => $delegation->status,
                        ])
                        ->all())
                    ->table([
                        TableColumn::make(__('delegation.fields.grantor')),
                        TableColumn::make(__('delegation.fields.delegate')),
                        TableColumn::make(__('delegation.fields.scope_type')),
                        TableColumn::make(__('delegation.fields.period')),
                        TableColumn::make(__('delegation.fields.status')),
                    ])
                    ->schema([
                        TextEntry::make('grantor')->label(__('delegation.fields.grantor'))->icon(RecordLinks::PERSONNEL_ICON)->weight('medium'),
                        TextEntry::make('delegate')->label(__('delegation.fields.delegate'))->icon(RecordLinks::PERSONNEL_ICON),
                        TextEntry::make('scope')->label(__('delegation.fields.scope_type'))->badge()->color('gray'),
                        TextEntry::make('period')->label(__('delegation.fields.period'))->color('gray'),
                        TextEntry::make('status')->label(__('delegation.fields.status'))->badge(),
                    ])
                    ->placeholder(__('delegation.empty_open')),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('delegation.actions.close'))
            ->extraModalFooterActions(fn (): array => [
                Action::make('allDelegations')
                    ->label(__('delegation.actions.all'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color(ActionColors::VIEW)
                    ->url(DelegationResource::getUrl('index')),
                Action::make('newDelegation')
                    ->label(__('delegation.actions.new'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->color(ActionColors::CREATE)
                    ->visible(DelegationResource::canCreate())
                    ->url(DelegationResource::getUrl('create')),
            ]);
    }

    /** "01.10.2026 09:00 – 15.10.2026 18:00"; bitis yoksa yalniz baslangic. */
    private static function period(Delegation $delegation): string
    {
        $from = DisplayTime::format($delegation->valid_from);
        $until = $delegation->valid_until !== null ? DisplayTime::format($delegation->valid_until) : null;

        return $until !== null ? $from.' – '.$until : (string) $from;
    }
}
