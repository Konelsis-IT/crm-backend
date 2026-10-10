<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Acquisition\ProposalService;
use App\Services\Platform\FeatureFlags;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component as LivewireComponent;

/**
 * Durum dugmesi (D-161, 6 Ekim 2026 kullanici talimati: "her durumun bir rengi
 * olacak ... Duzenle denildiginde sadece durum degistirilebilir olacak, ama bu
 * teklif - potansiyel is versiyonunu guncelletmeyecek, etki etmeyecek").
 *
 * D-182 (9 Ekim 2026, D-178'in duzeltmesi; kullanici: "Ben degisiklikleri
 * kaydet butonunun yanina koy demistim ... Bu teklifin durumu Gonderildi degil,
 * Verilen teklif. Ve action'a koyacaksin, dropdown ile secilecek, bu kadar
 * basit."):
 *
 * - Durum her zaman sayfa basliginda tek bir acilir dugmedir: etiketi su anki
 *   durum, durumun simgesi ve rengi, sonunda asagi ok; menude gecilebilecek
 *   durumlar kendi simge ve renkleriyle. Formun icinde ayri "Durum" bolumu yok.
 * - Teklifte gosterilen ve degistirilen durum Teklif durumudur
 *   (proposals.offer_status: Verilecek / Verilen / Onaylandi / Kacan firsat),
 *   surum durumu (Gonderildi...) degil. Kurallar ProposalService::offerTargets /
 *   changeOfferStatus'ta; yeni teklif surumu acilmaz.
 * - Potansiyel is: BusinessCaseService::changeStage (Kaybedildi -> teklifler
 *   "Kacan firsat"); "Operasyona devredildi" elle secilmez.
 * - Duzenleme sayfasi: dugme "Degisiklikleri kaydet"in hemen yaninda, her zaman
 *   (kodla gider). Detay sayfasi: ozellik acquisition.quick_status acikken
 *   ayni dugme (surum 2.5), kapaliyken D-161'deki sabit dugme.
 * - Onay penceresi: potansiyel is Kazanildi / Kaybedildi / Iptal edildi;
 *   teklif Onaylandi / Kacan firsat. Kaybedildi, Iptal ve Kacan firsatta
 *   istege bagli gerekce yazilir.
 */
final class StatusButton
{
    /** Onay penceresi acan potansiyel is durumlari (gerekce alaniyla). */
    private const CASE_CONFIRM = [AcquisitionStage::Won, AcquisitionStage::Lost, AcquisitionStage::Cancelled];

    /** Onay penceresi acan teklif durumlari (D-182). */
    private const OFFER_CONFIRM = [OfferStatus::Approved, OfferStatus::Lost];

    /** Detayda durum menusu acik mi (D-178, surum 2.5). */
    public static function quickEnabled(): bool
    {
        return FeatureFlags::enabled(Feature::AcquisitionQuickStatus);
    }

    /**
     * Potansiyel isin baslik dugmesi. $gated: detay sayfasi (ozellik kapaliyken
     * D-161 sabit dugmesi); duzenleme sayfasi false verir (kodla gider, D-182).
     * Gecis ya da yetki yoksa sabit dugme gorunur.
     *
     * @return list<Action|ActionGroup>
     */
    public static function businessCaseHeader(BusinessCase $case, bool $gated = true): array
    {
        if ($gated && ! self::quickEnabled()) {
            return [self::businessCase()];
        }

        $available = static fn (): bool => self::caseTargets($case) !== [] && Gate::allows('update', $case);

        return [
            ActionGroup::make(self::caseTargetActions($case))
                ->label(fn (): string => __('business_case.status.menu_label', ['status' => $case->acquisition_stage->getLabel()]))
                ->icon(fn (): Heroicon => self::caseIcon($case->acquisition_stage))
                ->color(fn (): string => $case->acquisition_stage->getColor())
                ->tooltip(__('business_case.status.menu_tooltip'))
                ->button()
                ->visible($available),
            self::businessCase()->visible(fn (): bool => ! $available()),
        ];
    }

    /**
     * Teklif detayinin baslik dugmesi (D-182): ozellik acikken Teklif durumu
     * menusu, kapaliyken D-161'deki sabit (surum durumu) dugmesi.
     *
     * @return list<Action|ActionGroup>
     */
    public static function proposalHeader(Proposal $proposal): array
    {
        if (! self::quickEnabled()) {
            return [self::proposal()];
        }

        return self::offerStatusHeader($proposal);
    }

    /**
     * Teklif durumu dugmesi (D-182): gecis ve yetki varsa acilir menu, yoksa
     * ayni gorunumde sabit dugme. Teklif duzenle ve teklif detayi kullanir.
     *
     * @return list<Action|ActionGroup>
     */
    public static function offerStatusHeader(Proposal $proposal): array
    {
        $available = static fn (): bool => self::offerTargets($proposal) !== [] && Gate::allows('update', $proposal);

        return [
            self::offerStatusAction($proposal)->visible($available),
            self::offerStatusFixed($proposal)->visible(fn (): bool => ! $available()),
        ];
    }

    /**
     * Acilir Teklif durumu dugmesi: etiket su anki durum (simge, renk, asagi
     * ok), menude gecilebilecek durumlar. Secim hemen kaydedilir.
     */
    public static function offerStatusAction(Proposal $proposal): ActionGroup
    {
        return ActionGroup::make(self::offerTargetActions($proposal))
            ->label(fn (): string => __('proposal.status.menu_label', ['status' => self::offerLabel($proposal->offer_status)]))
            ->icon(fn (): Heroicon => self::offerIcon($proposal->offer_status))
            ->color(fn (): string => $proposal->offer_status?->getColor() ?? 'gray')
            ->tooltip(__('proposal.status.menu_tooltip'))
            ->button();
    }

    /** Sabit (tiklanmaz) Teklif durumu dugmesi: gecis ya da yetki yokken. */
    private static function offerStatusFixed(Proposal $proposal): Action
    {
        return Action::make('offer_status')
            ->label(fn (): string => self::offerLabel($proposal->offer_status))
            ->icon(fn (): Heroicon => self::offerIcon($proposal->offer_status))
            ->color(fn (): string => $proposal->offer_status?->getColor() ?? 'gray')
            ->tooltip(fn (): string => self::offerTargets($proposal) === []
                ? __('proposal.status.no_targets')
                : __('business_case.status.no_permission'))
            ->extraAttributes(['class' => 'kc-status-button kc-status-fixed'])
            ->disabled();
    }

    /** Sabit (tiklanmaz) durum dugmesi: durumun adi ve rengi (D-161). */
    public static function businessCase(): Action
    {
        return Action::make('case_status')
            ->label(fn (BusinessCase $record): string => (string) $record->acquisition_stage->getLabel())
            ->icon(fn (BusinessCase $record): Heroicon => self::caseIcon($record->acquisition_stage))
            ->color(fn (BusinessCase $record): string => $record->acquisition_stage->getColor())
            ->tooltip(fn (BusinessCase $record): string => match (true) {
                self::caseTargets($record) === [] => __('business_case.status.no_targets'),
                Gate::denies('update', $record) => __('business_case.status.no_permission'),
                default => __('business_case.status.fixed'),
            })
            ->extraAttributes(['class' => 'kc-status-button kc-status-fixed'])
            ->disabled();
    }

    /** Ozellik kapaliyken teklif detayindaki D-161 sabit dugmesi (surum durumu). */
    public static function proposal(): Action
    {
        return Action::make('proposal_status')
            ->label(fn (Proposal $record): string => (string) (self::versionStatus($record)?->getLabel() ?? __('proposal.status.no_version')))
            ->icon(fn (Proposal $record): Heroicon => self::versionStatus($record) !== null ? self::proposalIcon(self::versionStatus($record)) : Heroicon::OutlinedFlag)
            ->color(fn (Proposal $record): string => self::versionStatus($record)?->getColor() ?? 'gray')
            ->tooltip(__('proposal.status.fixed'))
            ->extraAttributes(['class' => 'kc-status-button kc-status-fixed'])
            ->disabled();
    }

    /**
     * Potansiyel isin her hedef durumu bir eylem; yalniz izin verilenler gorunur.
     *
     * @return list<Action>
     */
    private static function caseTargetActions(BusinessCase $case): array
    {
        $actions = [];

        foreach (AcquisitionStage::cases() as $target) {
            if ($target === AcquisitionStage::HandoverAccepted) {
                continue;
            }

            $action = Action::make('case_status_'.$target->value)
                ->label((string) $target->getLabel())
                ->icon(self::caseIcon($target))
                ->color($target->getColor())
                ->visible(fn (): bool => in_array($target, self::caseTargets($case), true) && Gate::allows('update', $case))
                ->action(function (array $data, LivewireComponent $livewire) use ($case, $target): void {
                    try {
                        app(BusinessCaseService::class)->changeStage($case, $target, filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);

                        return;
                    }

                    DomainNotifications::success(__('business_case.messages.status_changed'));
                    self::afterChange($case, $livewire);
                });

            if (in_array($target, self::CASE_CONFIRM, true)) {
                $action
                    ->requiresConfirmation()
                    ->modalHeading(__('business_case.status.confirm_heading', ['status' => $target->getLabel()]))
                    ->modalDescription(fn (): string => __('business_case.status.confirm_description', [
                        'from' => $case->acquisition_stage->getLabel(),
                        'to' => $target->getLabel(),
                    ]).($target === AcquisitionStage::Lost ? ' '.__('business_case.status.lost_note') : ''))
                    ->modalIcon(self::caseIcon($target))
                    ->modalSubmitActionLabel(__('app.actions.save'))
                    ->schema(self::reasonSchema(__('business_case.fields.reason')));
            }

            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * Teklif durumunun her hedefi bir eylem (D-182); yalniz izin verilenler
     * gorunur. Onaylandi ve Kacan firsat onay penceresi acar.
     *
     * @return list<Action>
     */
    private static function offerTargetActions(Proposal $proposal): array
    {
        $actions = [];

        foreach (OfferStatus::cases() as $target) {
            $action = Action::make('offer_status_'.$target->value)
                ->label((string) $target->getLabel())
                ->icon(self::offerIcon($target))
                ->color($target->getColor())
                ->visible(fn (): bool => in_array($target, self::offerTargets($proposal), true) && Gate::allows('update', $proposal))
                ->action(function (array $data, LivewireComponent $livewire) use ($proposal, $target): void {
                    try {
                        app(ProposalService::class)->changeOfferStatus($proposal, $target, filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);

                        return;
                    }

                    DomainNotifications::success(__('proposal.messages.offer_status_changed', ['status' => $target->getLabel()]));
                    self::afterChange($proposal, $livewire);
                });

            if (in_array($target, self::OFFER_CONFIRM, true)) {
                $action
                    ->requiresConfirmation()
                    ->modalHeading(__('proposal.status.confirm_heading', ['status' => $target->getLabel()]))
                    ->modalDescription(fn (): string => __('proposal.status.confirm_description', [
                        'from' => self::offerLabel($proposal->offer_status),
                        'to' => $target->getLabel(),
                    ]).' '.__($target === OfferStatus::Approved ? 'proposal.status.approved_note' : 'proposal.status.lost_note'))
                    ->modalIcon(self::offerIcon($target))
                    ->modalSubmitActionLabel(__('app.actions.save'));

                if ($target === OfferStatus::Lost) {
                    $action->schema(self::reasonSchema(__('business_case.fields.reason')));
                }
            }

            $actions[] = $action;
        }

        return $actions;
    }

    /** Onay penceresindeki istege bagli gerekce. */
    private static function reasonSchema(string $label): Closure
    {
        return static fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
            Textarea::make('reason')
                ->label($label)
                ->maxLength(500)
                ->rows(2),
        ]));
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
     * @return list<OfferStatus>
     */
    private static function offerTargets(Proposal $proposal): array
    {
        return app(ProposalService::class)->offerTargets($proposal);
    }

    private static function versionStatus(Proposal $proposal): ?ProposalVersionStatus
    {
        return $proposal->currentVersion?->status;
    }

    private static function offerLabel(?OfferStatus $status): string
    {
        return $status !== null ? (string) $status->getLabel() : __('proposal.status.no_offer_status');
    }

    /** Teklif durumu simgeleri (D-182); renkler enum'da (D-161). */
    private static function offerIcon(?OfferStatus $status): Heroicon
    {
        return match ($status) {
            OfferStatus::ToBeSubmitted => Heroicon::OutlinedClock,
            OfferStatus::Submitted => Heroicon::OutlinedPaperAirplane,
            OfferStatus::Approved => Heroicon::OutlinedCheckBadge,
            OfferStatus::Lost => Heroicon::OutlinedXCircle,
            null => Heroicon::OutlinedFlag,
        };
    }

    /** Durum simgeleri (D-178); renkler enum'da (D-161). */
    private static function caseIcon(AcquisitionStage $stage): Heroicon
    {
        return match ($stage) {
            AcquisitionStage::BusinessDevelopment => Heroicon::OutlinedLightBulb,
            AcquisitionStage::OfferPreparation => Heroicon::OutlinedPencilSquare,
            AcquisitionStage::OfferReview => Heroicon::OutlinedMagnifyingGlass,
            AcquisitionStage::Submitted => Heroicon::OutlinedPaperAirplane,
            AcquisitionStage::Negotiation => Heroicon::OutlinedChatBubbleLeftRight,
            AcquisitionStage::Won => Heroicon::OutlinedTrophy,
            AcquisitionStage::HandoverPreparing => Heroicon::OutlinedClipboardDocumentList,
            AcquisitionStage::HandoverReview => Heroicon::OutlinedClipboardDocumentCheck,
            AcquisitionStage::HandoverAccepted => Heroicon::OutlinedCheckBadge,
            AcquisitionStage::Lost => Heroicon::OutlinedXCircle,
            AcquisitionStage::Cancelled => Heroicon::OutlinedNoSymbol,
        };
    }

    private static function proposalIcon(ProposalVersionStatus $status): Heroicon
    {
        return match ($status) {
            ProposalVersionStatus::Draft => Heroicon::OutlinedPencilSquare,
            ProposalVersionStatus::Review => Heroicon::OutlinedMagnifyingGlass,
            ProposalVersionStatus::Approved => Heroicon::OutlinedCheckCircle,
            ProposalVersionStatus::Submitted => Heroicon::OutlinedPaperAirplane,
            ProposalVersionStatus::Superseded => Heroicon::OutlinedArrowPath,
            ProposalVersionStatus::Withdrawn => Heroicon::OutlinedArrowUturnLeft,
        };
    }

    /**
     * Detay sayfasi yeniden acilir (kartlar, "Bu is nerede?" ve menu yeni
     * durumla gelir). Duzenleme sayfasinda kayit tazelenir ve yalniz formun
     * surum kontrolu (row_version) guncellenir ki sonraki "Kaydet" eski surum
     * diye reddedilmesin; formun diger alanlari (kaydedilmemis girisler)
     * olduklari gibi kalir. Baslik dugmesi bir sonraki cizimde yeni durumu
     * gosterir.
     */
    private static function afterChange(BusinessCase|Proposal $record, LivewireComponent $livewire): void
    {
        $record->refresh();

        if ($record instanceof Proposal) {
            $record->unsetRelation('currentVersion');
            $record->unsetRelation('businessCase');
        }

        if ($livewire instanceof ViewRecord) {
            $livewire->redirect($livewire::getResource()::getUrl('view', ['record' => $record]));

            return;
        }

        if (! $livewire instanceof EditRecord) {
            return;
        }

        if (array_key_exists('row_version', (array) ($livewire->data ?? []))) {
            $livewire->refreshFormData(['row_version']);
        }
    }
}
