<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Acquisition\ProposalStatus;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderNotice;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * "Bu is nerede?" (D-143, 29 Eylul 2026 kullanici karari: UI Deneme > Adim
 * denemeleri 12 secildi). Teklif ve potansiyel is detay sayfalarinda eski
 * "Is akisi" sihirbazinin yerine dikey hat: Potansiyel is -> Teklif(ler) ->
 * Proje. Bulunulan sayfanin duragi kart ve "Buradasiniz"; diger duraklar
 * baslik, acikca yazan baglanti ve ozet etiketler (bos degerler gosterilmez,
 * etiketin ustune gelince ne oldugu yazar). Proje yoksa acilis kosullari ve
 * yetki varsa gercek "Projeye donustur". Duz yesil: var olan kayit, kirmizi:
 * bu sayfa, gri: henuz yok. Stiller konelsis.css (kc-metro, D-141/D-143).
 *
 * B43 (D-155): zincir ihaleyle baslar. Ihale sayfasinda ilk durak "Buradasiniz"
 * ihale; potansiyel is ve teklif sayfalarinda bagli ihaleler en ustte durur.
 */
final class DealTrack
{
    /** @var list<string> */
    private const RELATIONS = [
        'codes',
        'primaryParty',
        'owner',
        'scopes',
        'proposals.currentVersion',
        'project.businessCode',
        'project.projectManager',
        'operationHandoff',
    ];

    public function forProposal(Proposal $proposal): ?Component
    {
        $proposal->loadMissing(['currentVersion', ...array_map(static fn (string $relation): string => 'businessCase.'.$relation, self::RELATIONS)]);
        $case = $proposal->businessCase;

        if ($case === null) {
            return null;
        }

        return $this->section([
            ...$this->tenderStops($case),
            $this->caseStop($case),
            $this->proposalHere($proposal, $case),
            $this->projectStop($case, $proposal),
        ]);
    }

    public function forBusinessCase(BusinessCase $case): Component
    {
        $case->loadMissing(self::RELATIONS);

        return $this->section([
            ...$this->tenderStops($case),
            $this->caseHere($case),
            $this->proposalsStop($case),
            $this->projectStop($case, null),
        ]);
    }

    /**
     * Ihale sayfasi (B43, D-155: zincir Ihale -> Potansiyel is -> Teklif ->
     * Proje): "Buradasiniz" bu ihale; potansiyel is yoksa ihaleden acma yolu.
     */
    public function forTender(TenderNotice $notice): Component
    {
        $case = $notice->businessCase;

        if ($case === null) {
            return $this->section([
                $this->tenderHere($notice),
                $this->noCaseStop($notice),
            ]);
        }

        $case->loadMissing(self::RELATIONS);

        return $this->section([
            $this->tenderHere($notice),
            $this->caseStop($case),
            $this->proposalsStop($case),
            $this->projectStop($case, null),
        ]);
    }

    /**
     * Potansiyel is / teklif sayfasinda bagli ihaleler (B43); ihale yoksa durak
     * gosterilmez (her is ihaleden gelmez).
     *
     * @return list<Component>
     */
    private function tenderStops(BusinessCase $case): array
    {
        if (! SchemaReadiness::hasBatch('B43')) {
            return [];
        }

        $case->loadMissing(['tenderNotices.source', 'tenderNotices.issuerParty']);

        return $case->tenderNotices->sortByDesc('captured_at')->values()->map(fn (TenderNotice $notice): Component => Group::make([
            Flex::make([
                Text::make(__('deal_track.tender').' · '.trim((filled($notice->external_notice_id) ? $notice->external_notice_id.' · ' : '').$notice->title))->weight(FontWeight::Bold),
                ...($this->canView($notice) ? [Actions::make([$this->link('track_tender_'.$notice->getKey(), __('deal_track.go_tender'), TenderNoticeResource::getUrl('view', ['record' => $notice]))])->grow(false)] : []),
            ]),
            $this->chips([
                [__('deal_track.chips.stage'), (string) ($notice->status?->getLabel() ?? ''), Heroicon::OutlinedFlag, $notice->status?->getColor()],
                [__('deal_track.chips.source'), (string) ($notice->source?->name_tr ?? ''), Heroicon::OutlinedGlobeAlt, null],
                [__('deal_track.chips.issuer'), (string) ($notice->issuerParty?->display_name ?? ''), Heroicon::OutlinedBuildingOffice2, null],
            ]),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-done']))->all();
    }

    /** Ihale sayfasinda "Buradasiniz" karti: durum cumlesi ve ozet etiketler. */
    private function tenderHere(TenderNotice $notice): Component
    {
        $version = $notice->currentVersion;

        return Group::make([
            $this->hereTitle(__('deal_track.tender').(filled($notice->external_notice_id) ? ' · '.$notice->external_notice_id : '')),
            Text::make(__('deal_track.tender_now', ['status' => (string) ($notice->status?->getLabel() ?? '-')])),
            $this->chips([
                [__('deal_track.chips.source'), (string) ($notice->source?->name_tr ?? ''), Heroicon::OutlinedGlobeAlt, null],
                [__('deal_track.chips.issuer'), (string) ($notice->issuerParty?->display_name ?? ''), Heroicon::OutlinedBuildingOffice2, null],
                [__('deal_track.chips.published'), $version?->published_on?->format('d.m.Y') ?? '', Heroicon::OutlinedCalendarDays, null],
                (bool) $notice->getAttribute('is_draft') ? [__('deal_track.chips.draft'), (string) __('app.values.draft'), Heroicon::OutlinedPencilSquare, 'gray'] : null,
            ]),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-current']);
    }

    /** Ihalenin potansiyel isi yoksa: gri durak ve "Bu ihaleden potansiyel is olustur". */
    private function noCaseStop(TenderNotice $notice): Component
    {
        $canCreate = Gate::allows('create', BusinessCase::class);

        return Group::make([
            Text::make(__('deal_track.case_none'))->weight(FontWeight::Bold)->color('gray'),
            Text::make(__('deal_track.case_none_help'))->color('gray'),
            ...($canCreate ? [Actions::make([
                Action::make('track_create_case')
                    ->label(__('tender_notice.actions.create_case'))
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->color('gray')
                    ->url(BusinessCaseResource::getUrl('create', [BusinessCaseWizard::QUERY_TENDER => $notice->getKey()])),
            ])] : []),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-todo']);
    }

    /** @param  list<Component>  $stops */
    private function section(array $stops): Section
    {
        return Section::make(__('deal_track.heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([Group::make($stops)->extraAttributes(['class' => 'kc-metro'])]);
    }

    /** Teklif sayfasinda potansiyel is duragi: baslik, baglanti, ozet etiketler. */
    private function caseStop(BusinessCase $case): Component
    {
        return Group::make([
            Flex::make([
                Text::make(__('deal_track.case').' · '.$this->caseCode($case).' · '.$case->title)->weight(FontWeight::Bold),
                ...($this->canView($case) ? [Actions::make([$this->link('track_case', __('deal_track.go_case'), BusinessCaseResource::getUrl('view', ['record' => $case]))])->grow(false)] : []),
            ]),
            $this->chips([
                [__('deal_track.chips.customer'), (string) ($case->primaryParty?->display_name ?? ''), Heroicon::OutlinedBuildingOffice2, null],
                [__('deal_track.chips.stage'), (string) $case->acquisition_stage->getLabel(), Heroicon::OutlinedFlag, $case->acquisition_stage->getColor()],
                [__('deal_track.chips.type'), $this->projectType($case), Heroicon::OutlinedCube, null],
                ...$this->scopeChips($case),
                [__('deal_track.chips.value'), $this->money($case->estimated_value, $case->currency_code), Heroicon::OutlinedBanknotes, null],
                [__('deal_track.chips.proposals'), __('deal_track.proposal_count', ['count' => $case->proposals->count()]), Heroicon::OutlinedClipboardDocumentList, null],
                [__('deal_track.chips.owner'), (string) ($case->owner?->full_name ?? ''), Heroicon::OutlinedUserCircle, null],
                $this->lastMeetingChip($case),
            ]),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-done']);
    }

    /**
     * Potansiyel is sayfasinda "Buradasiniz" karti: durum, siradaki durumlar,
     * sonuc ve son gorusme (ust kartta olan musteri / tutar / sorumlu tekrarlanmaz).
     */
    private function caseHere(BusinessCase $case): Component
    {
        $stage = $case->acquisition_stage;
        $next = array_values(array_filter(
            $stage->allowedTargets(),
            static fn (AcquisitionStage $target): bool => ! in_array($target, [AcquisitionStage::Lost, AcquisitionStage::Cancelled], true),
        ));

        $sentence = __('deal_track.case_now', ['stage' => $stage->getLabel()]).' · '.($next === []
            ? __('deal_track.case_final')
            : __('deal_track.case_next', ['stages' => implode(' / ', array_map(static fn (AcquisitionStage $target): string => (string) $target->getLabel(), $next))]));

        return Group::make([
            $this->hereTitle(__('deal_track.case').' · '.$this->caseCode($case)),
            Text::make($sentence),
            $this->chips([
                [__('deal_track.chips.outcome'), (string) ($case->outcome?->getLabel() ?? ''), Heroicon::OutlinedFlag, $case->outcome?->getColor()],
                $this->lastMeetingChip($case),
            ]),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-current']);
    }

    /** Teklif sayfasinda "Buradasiniz" karti: durum cumlesi, ozet etiketler, isin diger teklifleri. */
    private function proposalHere(Proposal $proposal, BusinessCase $case): Component
    {
        $state = $this->state($proposal, $case);
        $version = $proposal->currentVersion;
        $submitted = $this->submittedAt($proposal);
        $siblings = $case->proposals
            ->reject(fn (Proposal $sibling): bool => $sibling->is($proposal))
            ->sortBy('proposal_no')
            ->values();

        return Group::make([
            $this->hereTitle(__('deal_track.proposal').' · '.$proposal->proposal_no),
            Text::make(__('deal_track.state.'.$state.'.headline').' — '.__('deal_track.state.'.$state.'.detail', [
                'date' => $submitted?->format('d.m.Y') ?? '-',
                'wait' => $this->wait($submitted),
                'code' => $case->project?->businessCode?->formatted_code ?? '-',
            ])),
            $this->chips([
                [__('deal_track.chips.offer_status'), (string) ($proposal->offer_status?->getLabel() ?? ''), Heroicon::OutlinedFlag, $proposal->offer_status?->getColor()],
                [__('deal_track.chips.version'), $version === null ? '' : __('proposal.steps.version', ['no' => $version->version_no, 'status' => (string) ($version->status?->getLabel() ?? '-')]), Heroicon::OutlinedDocumentDuplicate, null],
                [__('deal_track.chips.sent'), $submitted !== null ? __('deal_track.sent_on', ['date' => $submitted->format('d.m.Y')]) : __('deal_track.not_sent'), Heroicon::OutlinedPaperAirplane, null],
                [__('deal_track.chips.validity'), $version?->validity_until !== null ? __('deal_track.valid_until', ['date' => $version->validity_until->format('d.m.Y')]) : '', Heroicon::OutlinedCalendarDays, null],
                $proposal->is_selected && $siblings->isNotEmpty() ? [__('deal_track.chips.selected'), __('deal_track.selected'), Heroicon::OutlinedCheckCircle, 'success'] : null,
            ]),
            ...($siblings->isNotEmpty() ? [
                Text::make(__('deal_track.other_proposals'))->size(TextSize::ExtraSmall)->weight(FontWeight::SemiBold)->color('gray'),
                ...$siblings->map(fn (Proposal $sibling): Component => $this->proposalRow($sibling, compact: true))->all(),
            ] : []),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-current']);
    }

    /** Potansiyel is sayfasinda teklifler duragi: butun teklifler kisa satirlarla ve "Teklif olustur". */
    private function proposalsStop(BusinessCase $case): Component
    {
        $proposals = $case->proposals->sortBy('proposal_no')->values();
        $canCreate = Gate::allows('create', Proposal::class);

        return Group::make([
            Flex::make([
                Text::make(__('deal_track.proposals').' · '.__('deal_track.proposal_count', ['count' => $proposals->count()]))
                    ->weight(FontWeight::Bold)
                    ->color($proposals->isEmpty() ? 'gray' : null),
                ...($canCreate ? [Actions::make([
                    Action::make('track_create_proposal')
                        ->label(__('deal_track.create_proposal'))
                        ->icon(Heroicon::OutlinedPlus)
                        ->color('gray')
                        ->url(ProposalResource::getUrl('create', ['business_case_id' => $case->getKey()])),
                ])->grow(false)] : []),
            ]),
            ...($proposals->isEmpty()
                ? [Text::make(__('deal_track.no_proposal'))->color('gray')]
                : $proposals->map(fn (Proposal $proposal): Component => $this->proposalRow($proposal, compact: false, multiple: $proposals->count() > 1))->all()),
        ])->extraAttributes(['class' => 'kc-metro-stop '.($proposals->isEmpty() ? 'kc-todo' : 'kc-done')]);
    }

    /** Teklif satiri: teklife giden baglanti ve ozet etiketler. */
    private function proposalRow(Proposal $proposal, bool $compact, bool $multiple = false): Component
    {
        $version = $proposal->currentVersion;
        $submitted = $this->submittedAt($proposal);
        $label = $proposal->proposal_no.' · '.$proposal->title;

        return Flex::make([
            $this->canView($proposal)
                ? Actions::make([$this->link('track_proposal_'.$proposal->getKey(), $label, ProposalResource::getUrl('view', ['record' => $proposal]), Heroicon::OutlinedClipboardDocumentList)])->grow(false)
                : Text::make($label)->grow(false),
            $this->chips($compact ? [
                [__('deal_track.chips.offer_status'), (string) ($proposal->offer_status?->getLabel() ?? ''), Heroicon::OutlinedFlag, $proposal->offer_status?->getColor()],
                [__('deal_track.chips.sent'), $submitted !== null ? __('deal_track.sent_on', ['date' => $submitted->format('d.m.Y')]) : '', Heroicon::OutlinedPaperAirplane, null],
            ] : [
                [__('deal_track.chips.offer_status'), (string) ($proposal->offer_status?->getLabel() ?? ''), Heroicon::OutlinedFlag, $proposal->offer_status?->getColor()],
                [__('deal_track.chips.version'), $version === null ? '' : __('proposal.steps.version', ['no' => $version->version_no, 'status' => (string) ($version->status?->getLabel() ?? '-')]), Heroicon::OutlinedDocumentDuplicate, null],
                [__('deal_track.chips.sent'), $submitted !== null ? __('deal_track.sent_on', ['date' => $submitted->format('d.m.Y')]) : __('deal_track.not_sent'), Heroicon::OutlinedPaperAirplane, null],
                [__('deal_track.chips.price'), $this->money($version?->total_price, $version?->currency_code), Heroicon::OutlinedBanknotes, null],
                $proposal->is_selected && $multiple ? [__('deal_track.chips.selected'), __('deal_track.selected'), Heroicon::OutlinedCheckCircle, 'success'] : null,
            ]),
        ])->from('md')->extraAttributes(['class' => 'kc-list-row']);
    }

    /**
     * Proje duragi: proje varsa kunyesi ve baglanti; yoksa acilis kosullari ve
     * gercek "Projeye donustur" (yetki ve kosul BusinessCaseWizard::convertAction'da);
     * is kaybedildiyse / iptalse "acilmayacak".
     */
    private function projectStop(BusinessCase $case, ?Proposal $proposal): Component
    {
        $project = $case->project;

        if ($project !== null) {
            $site = trim(implode(' / ', array_filter([$project->site_district, $project->site_city])));
            $planned = $project->planned_start_on === null && $project->planned_finish_on === null
                ? ''
                : ($project->planned_start_on?->format('d.m.Y') ?? '-').' – '.($project->planned_finish_on?->format('d.m.Y') ?? '-');

            return Group::make([
                Flex::make([
                    Text::make(__('deal_track.project').' · '.trim(($project->businessCode?->formatted_code ?? '').' · '.$project->name, ' ·'))->weight(FontWeight::Bold),
                    ...($this->canView($project) ? [Actions::make([$this->link('track_project', __('deal_track.go_project'), ProjectResource::getUrl('view', ['record' => $project]))])->grow(false)] : []),
                ]),
                $this->chips([
                    [__('deal_track.chips.stage'), (string) $project->status->getLabel(), Heroicon::OutlinedFlag, $project->status->getColor()],
                    [__('deal_track.chips.manager'), (string) ($project->projectManager?->full_name ?? ''), Heroicon::OutlinedUserCircle, null],
                    [__('deal_track.chips.planned'), $planned, Heroicon::OutlinedCalendarDays, null],
                    [__('deal_track.chips.site'), $site, Heroicon::OutlinedMapPin, null],
                ]),
            ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-done']);
        }

        $closed = in_array($case->outcome, [BusinessOutcome::Lost, BusinessOutcome::Cancelled], true)
            || ($proposal !== null && in_array($this->state($proposal, $case), ['lost', 'cancelled'], true));

        if ($closed) {
            return Group::make([
                Text::make(__('deal_track.project_off'))->weight(FontWeight::Bold)->color('gray'),
                Text::make(__('deal_track.project_off_help'))->color('gray'),
            ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-off']);
        }

        $sent = $proposal !== null
            ? $this->submittedAt($proposal) !== null || in_array($proposal->status, [ProposalStatus::Submitted, ProposalStatus::Negotiation, ProposalStatus::Accepted], true)
            : $case->proposals->contains(fn (Proposal $item): bool => $this->submittedAt($item) !== null || in_array($item->status, [ProposalStatus::Submitted, ProposalStatus::Negotiation, ProposalStatus::Accepted], true));
        $won = $case->outcome === BusinessOutcome::Won;
        $handoff = $case->operationHandoff;

        return Group::make([
            Text::make(__('deal_track.project_none'))->weight(FontWeight::Bold)->color('gray'),
            Text::make(__('deal_track.project_none_help'))->color('gray'),
            $this->check($sent, __('deal_track.check_sent')),
            $this->check($won, __('deal_track.check_won')),
            ...($handoff !== null ? [$this->chips([[__('deal_track.chips.handoff'), __('deal_track.handoff', ['status' => (string) $handoff->status->getLabel()]), Heroicon::OutlinedArrowsRightLeft, $handoff->status->getColor()]])] : []),
            Actions::make([app(BusinessCaseWizard::class)->convertAction($case, 'track_convert', proposal: $proposal)]),
        ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-todo']);
    }

    /**
     * Teklifin durumu (durum cumlesi icin): project, cancelled, lost, won, sent, draft.
     */
    private function state(Proposal $proposal, BusinessCase $case): string
    {
        $status = $proposal->status;

        return match (true) {
            $case->project !== null => 'project',
            $case->outcome === BusinessOutcome::Cancelled => 'cancelled',
            $proposal->offer_status === OfferStatus::Lost || $case->outcome === BusinessOutcome::Lost || $status === ProposalStatus::Rejected => 'lost',
            $case->outcome === BusinessOutcome::Won || $status === ProposalStatus::Accepted => 'won',
            $this->submittedAt($proposal) !== null || in_array($status, [ProposalStatus::Submitted, ProposalStatus::Negotiation], true) => 'sent',
            default => 'draft',
        };
    }

    private function submittedAt(Proposal $proposal): ?Carbon
    {
        $value = $proposal->currentVersion?->submitted_at;

        return $value === null ? null : Carbon::parse($value)->timezone(DisplayTime::zone());
    }

    /** "bugun gonderildi" ya da "12 gundur cevap bekleniyor". */
    private function wait(?Carbon $submitted): string
    {
        if ($submitted === null) {
            return '';
        }

        $days = (int) $submitted->copy()->startOfDay()->diffInDays(Carbon::now(DisplayTime::zone())->startOfDay());

        return $days <= 0 ? __('deal_track.wait_today') : __('deal_track.wait_days', ['days' => $days]);
    }

    private function hereTitle(string $title): Flex
    {
        return Flex::make([
            Text::make($title)->weight(FontWeight::Bold)->size(TextSize::Large)->color('primary'),
            Text::make(__('deal_track.here'))->badge()->color('primary')->icon(Heroicon::OutlinedMapPin)->grow(false),
        ]);
    }

    /**
     * Ozet etiketler: [etiket (ipucu), deger, simge, renk|null]; bos degerler atlanir.
     *
     * @param  list<array{0: string, 1: string, 2: Heroicon, 3: string|null}|null>  $facts
     */
    private function chips(array $facts): Flex
    {
        $facts = array_values(array_filter(
            $facts,
            static fn (?array $fact): bool => $fact !== null && ! in_array(trim($fact[1]), ['', '-'], true),
        ));

        return Flex::make(array_map(
            static fn (array $fact): Text => Text::make($fact[1])
                ->badge()
                ->color($fact[3] ?? 'gray')
                ->icon($fact[2])
                ->tooltip($fact[0])
                ->grow(false),
            $facts,
        ))->extraAttributes(['class' => 'kc-chips']);
    }

    /** Kosul satiri; Filament rozet olmayan metne simge cizmedigi icin simge ayri. */
    private function check(bool $done, string $label): Flex
    {
        return Flex::make([
            Icon::make($done ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedMinusCircle)
                ->color($done ? 'success' : 'gray')
                ->grow(false),
            Text::make($label)->color($done ? 'success' : 'gray'),
        ])->extraAttributes(['class' => 'kc-check']);
    }

    private function link(string $name, string $label, string $url, Heroicon $icon = Heroicon::OutlinedArrowTopRightOnSquare): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->link()
            ->url($url);
    }

    /** @return array{0: string, 1: string, 2: Heroicon, 3: string|null}|null */
    private function lastMeetingChip(BusinessCase $case): ?array
    {
        $date = app(BusinessCaseQueries::class)->lastMeetingOn((int) $case->getKey());

        return $date === null ? null : [__('deal_track.chips.last_meeting'), __('deal_track.last_meeting', ['date' => $date->format('d.m.Y')]), Heroicon::OutlinedChatBubbleLeftRight, null];
    }

    private function caseCode(BusinessCase $case): string
    {
        return $case->caseCode()?->formatted_code ?? '-';
    }

    private function projectType(BusinessCase $case): string
    {
        $code = (string) ($case->project_type_code ?? '');

        if ($code === '') {
            return '';
        }

        return (string) (app(ProjectCatalogQueries::class)->componentDefinitionOptions()[$code] ?? $code);
    }

    /**
     * Proje tipleri: her tip kendi simgesi ve rengiyle ayri rozet (D-163; once
     * "GES + TM" tek rozetti).
     *
     * @return list<array{0: string, 1: string, 2: Heroicon, 3: ?string}>
     */
    private function scopeChips(BusinessCase $case): array
    {
        return $case->scopes
            ->map(static function ($scope): array {
                $type = $scope->scope_type instanceof ProjectScopeType ? $scope->scope_type : ProjectScopeType::tryFrom((string) $scope->scope_type);

                return [
                    (string) __('deal_track.chips.scopes'),
                    (string) ($type?->getLabel() ?? $scope->scope_type),
                    $type?->getIcon() ?? Heroicon::OutlinedSquares2x2,
                    $type?->getColor(),
                ];
            })
            ->values()
            ->all();
    }

    private function money(mixed $amount, ?string $currency): string
    {
        return $amount === null ? '' : Number::format((float) $amount, precision: 2, locale: 'tr').' '.($currency ?? '');
    }

    private function canView(mixed $record): bool
    {
        return Gate::allows('view', $record);
    }
}
