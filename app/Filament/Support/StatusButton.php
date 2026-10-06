<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\AbstractException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Acquisition\ProposalVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component as LivewireComponent;

/**
 * Durum dugmesi (D-161, 6 Ekim 2026 kullanici talimati: "her durumun bir rengi
 * olacak, goruntule ekraninda action buton olarak sabit, degistirilemez olarak
 * kalacak. Duzenle denildiginde sadece durum degistirilebilir olacak, ama bu
 * teklif - potansiyel is versiyonunu guncelletmeyecek, etki etmeyecek").
 *
 * - Detay sayfasinda: durumun adi ve rengiyle sabit (tiklanmaz) dugme.
 * - Duzenleme sayfasinda: ayni dugme tiklaninca izin verilen sonraki durumlar
 *   kendi renkleriyle secilir; durum hemen kaydedilir. Formdaki diger alanlar
 *   ve teklif surumu etkilenmez (yeni surum acilmaz).
 * - Potansiyel is: AcquisitionStage (BusinessCaseService::changeStage).
 *   "Operasyona devredildi" elle secilmez (devir kabuluyle gelir).
 * - Teklif: guncel surumun durumu (ProposalVersionService::changeStatus);
 *   potansiyel isin durumu ve Teklif durumu (Verilecek / Verilen) kendiliginden
 *   izler. "Yerini aldi" elle secilmez (yeni surum acilinca gelir).
 */
final class StatusButton
{
    public static function businessCase(bool $editable): Action
    {
        return Action::make('case_status')
            ->label(fn (BusinessCase $record): string => (string) $record->acquisition_stage->getLabel())
            ->icon(Heroicon::OutlinedFlag)
            ->color(fn (BusinessCase $record): string => $record->acquisition_stage->getColor())
            ->tooltip(fn (BusinessCase $record): string => ! $editable
                ? __('business_case.status.fixed')
                : (self::caseTargets($record) === [] ? __('business_case.status.no_targets') : __('business_case.status.change')))
            ->extraAttributes(['class' => $editable ? 'kc-status-button' : 'kc-status-button kc-status-fixed'])
            ->disabled(fn (BusinessCase $record): bool => ! $editable || self::caseTargets($record) === [] || ! Gate::allows('update', $record))
            ->modalHeading(__('business_case.status.modal_heading'))
            ->modalDescription(fn (BusinessCase $record): string => __('business_case.status.modal_description', ['status' => $record->acquisition_stage->getLabel()]))
            ->modalIcon(Heroicon::OutlinedFlag)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('app.actions.save'))
            ->schema(fn (BusinessCase $record): array => [
                self::picker(self::caseTargets($record)),
                Textarea::make('reason')
                    ->label(__('business_case.fields.reason'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (BusinessCase $record, array $data, LivewireComponent $livewire): void {
                $target = AcquisitionStage::tryFrom(self::value($data['target'] ?? null));

                if ($target === null) {
                    return;
                }

                try {
                    app(BusinessCaseService::class)->changeStage($record, $target, filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('business_case.messages.status_changed'));
                self::refresh($record, $livewire);
            });
    }

    public static function proposal(bool $editable): Action
    {
        return Action::make('proposal_status')
            ->label(fn (Proposal $record): string => (string) (self::versionStatus($record)?->getLabel() ?? __('proposal.status.no_version')))
            ->icon(Heroicon::OutlinedFlag)
            ->color(fn (Proposal $record): string => self::versionStatus($record)?->getColor() ?? 'gray')
            ->tooltip(fn (Proposal $record): string => ! $editable
                ? __('proposal.status.fixed')
                : (self::proposalTargets($record) === [] ? __('proposal.status.no_targets') : __('proposal.status.change')))
            ->extraAttributes(['class' => $editable ? 'kc-status-button' : 'kc-status-button kc-status-fixed'])
            ->disabled(fn (Proposal $record): bool => ! $editable
                || self::proposalTargets($record) === []
                || $record->currentVersion === null
                || ! Gate::allows('update', $record->currentVersion))
            ->modalHeading(__('proposal.status.modal_heading'))
            ->modalDescription(fn (Proposal $record): string => __('proposal.status.modal_description', [
                'status' => self::versionStatus($record)?->getLabel() ?? '-',
                'no' => $record->currentVersion?->version_no ?? '-',
            ]))
            ->modalIcon(Heroicon::OutlinedFlag)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('app.actions.save'))
            ->schema(fn (Proposal $record): array => [self::picker(self::proposalTargets($record))])
            ->action(function (Proposal $record, array $data, LivewireComponent $livewire): void {
                $target = ProposalVersionStatus::tryFrom(self::value($data['target'] ?? null));
                $version = $record->currentVersion;

                if ($target === null || $version === null) {
                    return;
                }

                try {
                    app(ProposalVersionService::class)->changeStatus($version, $target);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('proposal_version.messages.status_changed'));
                self::refresh($record, $livewire);
            });
    }

    /**
     * Izin verilen hedef durumlar, her biri kendi renginde.
     *
     * @param  list<AcquisitionStage|ProposalVersionStatus>  $targets
     */
    private static function picker(array $targets): ToggleButtons
    {
        $options = [];
        $colors = [];

        foreach ($targets as $target) {
            $options[$target->value] = (string) $target->getLabel();
            $colors[$target->value] = $target->getColor();
        }

        // kc-status-picker: secilmemis secenek de kendi renginde (cerceve, yazi).
        return ToggleButtons::make('target')
            ->label(__('business_case.status.target'))
            ->options($options)
            ->colors($colors)
            ->inline()
            ->extraAttributes(['class' => 'kc-status-picker'])
            ->required();
    }

    /**
     * @return list<AcquisitionStage>
     */
    private static function caseTargets(BusinessCase $case): array
    {
        return array_values(array_filter(
            $case->acquisition_stage->allowedTargets(),
            static fn (AcquisitionStage $target): bool => $target !== AcquisitionStage::HandoverAccepted,
        ));
    }

    /**
     * @return list<ProposalVersionStatus>
     */
    private static function proposalTargets(Proposal $proposal): array
    {
        $status = self::versionStatus($proposal);

        if ($status === null) {
            return [];
        }

        return array_values(array_filter(
            $status->allowedTargets(),
            static fn (ProposalVersionStatus $target): bool => $target !== ProposalVersionStatus::Superseded,
        ));
    }

    private static function versionStatus(Proposal $proposal): ?ProposalVersionStatus
    {
        return $proposal->currentVersion?->status;
    }

    /**
     * Kayit tazelenir; duzenleme sayfasinda formun surum kontrolu (row_version)
     * ve durumun degistirdigi Teklif durumu (offer_status) da guncellenir ki
     * sonraki "Kaydet" eski surum diye reddedilmesin ya da eski degeri geri
     * yazmasin. Formun diger alanlari (kaydedilmemis girisler) olduklari gibi kalir.
     */
    private static function refresh(BusinessCase|Proposal $record, LivewireComponent $livewire): void
    {
        $record->refresh();

        if ($record instanceof Proposal) {
            $record->unsetRelation('currentVersion');
        }

        if (! $livewire instanceof EditRecord) {
            return;
        }

        $paths = array_values(array_filter(
            ['row_version', 'offer_status'],
            static fn (string $path): bool => array_key_exists($path, (array) ($livewire->data ?? [])),
        ));

        if ($paths !== []) {
            $livewire->refreshFormData($paths);
        }
    }

    private static function value(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
