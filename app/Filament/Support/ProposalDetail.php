<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * Teklif detay sayfasi (22 Eylul 2026 kullanici karari): is dosyasi detay
 * sayfasiyla ayni yapi — kart, asama uyarisi ve "Is dosyasi -> Teklif ->
 * Proje" adimlari (D-112, D-113). Ustte teklif karti ve guncel surum karti yan
 * yana. Teklif sayfanin kendisi oldugu icin 2. adim bostur; 1. adim is
 * dosyasinin ozet karti (sihirbazdaki kart), 3. adim proje / bu teklifle
 * donusum (BusinessCaseWizard::projectStep). Surumler, dokumanlar ve raporlar
 * sayfanin alt listelerindedir.
 */
final class ProposalDetail
{
    /** Teklif karti: baslik, rozetler ve baglantili alanlar. */
    public function headerCard(Proposal $proposal): Component
    {
        $case = $proposal->businessCase;
        $customer = $case?->primaryParty;
        $owner = $proposal->owner;
        $version = $proposal->currentVersion;
        $project = $case?->project;
        $caseCode = $case?->offerCode()?->formatted_code;

        $badges = [
            Text::make((string) $proposal->proposal_no)->badge()->color('gray')->icon(Heroicon::OutlinedHashtag),
            Text::make((string) $proposal->status?->getLabel())->badge()->color($proposal->status?->getColor() ?? 'gray'),
        ];

        if (SchemaReadiness::hasBatch('B29') && $proposal->offer_status !== null) {
            $badges[] = Text::make((string) $proposal->offer_status->getLabel())->badge()->color($proposal->offer_status->getColor())->icon(Heroicon::OutlinedFlag);
        }

        if ($proposal->is_selected) {
            $badges[] = Text::make(__('proposal.fields.is_selected'))->badge()->color('success')->icon(Heroicon::OutlinedCheckCircle);
        }

        $entries = [
            TextEntry::make('business_case')
                ->label(__('proposal.fields.business_case'))
                ->state($case === null ? '-' : trim(($caseCode ?? '').' · '.$case->title, ' ·'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->iconColor('primary')
                ->color($case !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($case !== null && Gate::allows('view', $case) ? BusinessCaseResource::getUrl('view', ['record' => $case]) : null),
            TextEntry::make('customer')
                ->label(__('business_case.fields.primary_party'))
                ->state($customer?->display_name ?? '-')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->iconColor('primary')
                ->color($customer !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($customer !== null && Gate::allows('view', $customer) ? PartyResource::getUrl('view', ['record' => $customer]) : null),
            TextEntry::make('owner')
                ->label(__('proposal.fields.owner'))
                ->state($owner?->full_name ?? '-')
                ->icon(Heroicon::OutlinedUserCircle)
                ->iconColor('primary')
                ->color($owner !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($owner !== null && Gate::allows('view', $owner) ? PersonnelResource::getUrl('view', ['record' => $owner]) : null),
            TextEntry::make('total_price')
                ->label(__('proposal_version.fields.total_price'))
                ->state(self::money($version))
                ->icon(Heroicon::OutlinedBanknotes)
                ->iconColor('success')
                ->weight(FontWeight::SemiBold),
            TextEntry::make('current_version')
                ->label(__('proposal.fields.current_version'))
                ->state($version === null ? __('proposal.steps.no_version') : self::versionLine($version))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->iconColor('gray'),
            TextEntry::make('project')
                ->label(__('business_case.fields.project'))
                ->state($project?->name ?? __('business_case.steps.no_project'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->iconColor($project !== null ? 'success' : 'gray')
                ->color($project !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($project !== null ? ProjectResource::getUrl('view', ['record' => $project]) : null),
        ];

        return Section::make(__('proposal.sections.header'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->compact()
            ->components([
                Text::make(filled($proposal->title) ? (string) $proposal->title : (string) ($case?->title ?? '-'))->size(TextSize::Large)->weight(FontWeight::Bold),
                Flex::make($badges),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries),
            ]);
    }

    /**
     * Guncel surum karti: teklif kartinin yaninda yarim genislikte (22 Eylul
     * 2026): durum, tutarlar, gecerlilik, kritik rota, hazirlayan, gonderim, ozet.
     */
    public function versionCard(Proposal $proposal): Component
    {
        $version = $proposal->currentVersion;

        if ($version === null) {
            return Section::make(__('proposal.sections.current_version'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->compact()
                ->components([Text::make(__('proposal.steps.no_version'))->color('gray')]);
        }

        $entry = static fn (string $name, string $label, string $state, Heroicon $icon, string $color = 'gray'): TextEntry => TextEntry::make('version_'.$name)
            ->label($label)
            ->state($state)
            ->icon($icon)
            ->iconColor($color);

        return Section::make(__('proposal.sections.current_version'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->compact()
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->components([
                        TextEntry::make('version_status')
                            ->label(__('proposal_version.fields.status'))
                            ->state($version->status?->getLabel() ?? '-')
                            ->badge()
                            ->color($version->status?->getColor() ?? 'gray'),
                        $entry('total_price', __('proposal_version.fields.total_price'), self::money($version), Heroicon::OutlinedBanknotes, 'success'),
                        $entry('margin_pct', __('proposal_version.fields.margin_pct'), $version->margin_pct === null ? '-' : Number::format((float) $version->margin_pct, precision: 2, locale: 'tr').' %', Heroicon::OutlinedReceiptPercent),
                        $entry('validity_until', __('proposal_version.fields.validity_until'), $version->validity_until?->format('d.m.Y') ?? '-', Heroicon::OutlinedCalendarDays),
                        $entry('is_critical_route', __('proposal_version.fields.is_critical_route'), $version->is_critical_route ? __('export.values.yes') : __('export.values.no'), Heroicon::OutlinedExclamationTriangle, 'warning'),
                        $entry('preparer', __('proposal_version.fields.preparer'), (string) ($version->preparer?->full_name ?? '-'), Heroicon::OutlinedUser),
                        $entry('submitted_at', __('proposal_version.fields.submitted_at'), $version->submitted_at?->timezone(DisplayTime::zone())->format('d.m.Y H:i') ?? '-', Heroicon::OutlinedPaperAirplane),
                        $entry('summary', __('proposal_version.fields.summary'), filled($version->summary) ? (string) $version->summary : '-', Heroicon::OutlinedDocumentText)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Adim 1: is dosyasinin ozet karti (sihirbazdaki kartin aynisi) ve baglanti. */
    public function caseStep(Proposal $proposal): Step
    {
        $case = $proposal->businessCase;

        return Step::make(__('business_case.wizard.case'))
            ->id(BusinessCaseWizard::STEP_CASE)
            ->description($case === null ? '-' : trim(($case->offerCode()?->formatted_code ?? '').' · '.$case->title, ' ·'))
            ->icon(Heroicon::OutlinedBriefcase)
            ->completedIcon(Heroicon::OutlinedBriefcase)
            ->formWrapper(false)
            ->schema($case === null ? [] : [
                app(BusinessCaseWizard::class)->recordCaseSummary('proposal-view', (int) $case->getKey()),
                Actions::make([
                    Action::make('open_business_case')
                        ->label(__('proposal.actions.open_business_case'))
                        ->icon(Heroicon::OutlinedBriefcase)
                        ->color('gray')
                        ->visible(fn (): bool => Gate::allows('view', $case))
                        ->url(BusinessCaseResource::getUrl('view', ['record' => $case])),
                ]),
            ]);
    }

    /**
     * Adim 2 (22 Eylul 2026 kullanici karari): bos. Teklifin kendisi sayfanin
     * ustundeki kart ve guncel surum kartinda; surumler, dokumanlar ve raporlar
     * alt listelerde.
     */
    public function proposalStep(Proposal $proposal): Step
    {
        $version = $proposal->currentVersion;

        return Step::make(__('business_case.wizard.proposal'))
            ->id(BusinessCaseWizard::STEP_PROPOSAL)
            ->description($version === null ? __('proposal.steps.no_version') : self::versionLine($version))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
            ->formWrapper(false)
            ->schema([]);
    }

    /** Acilacak adim: istenen kimlik, yoksa proje varsa 3, degilse is dosyasi adimi (2. adim bostur). */
    public function startStep(Proposal $proposal, ?string $requestedId): int
    {
        if ($requestedId !== null) {
            $index = array_search($requestedId, BusinessCaseWizard::STEP_IDS, true);

            if ($index !== false) {
                return $index + 1;
            }
        }

        return $proposal->businessCase?->project !== null ? 3 : 1;
    }

    private static function versionLine(ProposalVersion $version): string
    {
        return __('proposal.steps.version', ['no' => $version->version_no, 'status' => (string) ($version->status?->getLabel() ?? '-')]);
    }

    private static function money(?ProposalVersion $version): string
    {
        if ($version === null || $version->total_price === null) {
            return '-';
        }

        return Number::format((float) $version->total_price, precision: 2, locale: 'tr').' '.($version->currency_code ?? '');
    }
}
