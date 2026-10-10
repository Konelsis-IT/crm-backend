<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\Acquisition\ProposalVersionScopeService;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * Teklif detay sayfasi (22 Eylul 2026 kullanici karari, D-112, D-113): ustte
 * teklif karti ve guncel surum karti yan yana. Potansiyel is / teklif / proje
 * iliskisi D-143'ten beri "Bu is nerede?" dikey hattinda (DealTrack); eski
 * "Is akisi" adimlari kaldirildi. Surumler, gorusme notlari, dokumanlar ve
 * raporlar sayfanin alt listelerindedir.
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
        $caseCode = $case?->caseCode()?->formatted_code;

        $badges = [
            Text::make((string) $proposal->proposal_no)->badge()->color('gray')->icon(Heroicon::OutlinedHashtag),
            // Bagli oldugu potansiyel isin kodu (D-132): hangi POTIS'e ait oldugu hep gorunur.
            ...($caseCode !== null ? [Text::make($caseCode)->badge()->color('warning')->icon(Heroicon::OutlinedBriefcase)] : []),
            Text::make((string) $proposal->status?->getLabel())->badge()->color($proposal->status?->getColor() ?? 'gray'),
        ];

        if (SchemaReadiness::hasBatch('B29') && $proposal->offer_status !== null) {
            $badges[] = Text::make((string) $proposal->offer_status->getLabel())->badge()->color($proposal->offer_status->getColor())->icon(Heroicon::OutlinedFlag);
        }

        // D-181: "Secili" rozeti kaldirildi; secili teklif kavrami yok.

        // D-185: bagli kayit metinleri kirmizi degil; normal yazi rengi, gri tur
        // simgesi (D-125), uzerine gelince alti cizili.
        $entries = [
            TextEntry::make('business_case')
                ->label(__('proposal.fields.business_case'))
                ->state($case === null ? '-' : trim(($caseCode ?? '').' · '.$case->title, ' ·'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->color($case !== null ? null : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($case !== null && Gate::allows('view', $case) ? BusinessCaseResource::getUrl('view', ['record' => $case]) : null),
            TextEntry::make('customer')
                ->label(__('business_case.fields.primary_party'))
                ->state($customer?->display_name ?? '-')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color($customer !== null ? null : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($customer !== null && Gate::allows('view', $customer) ? PartyResource::getUrl('view', ['record' => $customer]) : null),
            TextEntry::make('owner')
                ->label(__('proposal.fields.owner'))
                ->state($owner?->full_name ?? '-')
                ->icon(Heroicon::OutlinedUserCircle)
                ->color($owner !== null ? null : 'gray')
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
                ->state($project?->display_name ?? __('business_case.steps.no_project'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->iconColor($project !== null ? 'success' : 'gray')
                ->color($project !== null ? null : 'gray')
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
     * Surum karti: teklif kartinin yaninda yarim genislikte (22 Eylul 2026):
     * durum, tutarlar, gecerlilik, kritik rota, hazirlayan, gonderim, ozet.
     * $version verilmezse guncel surum; verilirse (D-158 surum penceresi) o surum.
     */
    public function versionCard(Proposal $proposal, ?ProposalVersion $version = null, string $prefix = 'version'): Component
    {
        $current = $version === null || (int) $version->getKey() === (int) $proposal->current_version_id;
        $version ??= $proposal->currentVersion;
        $heading = $current ? __('proposal.sections.current_version') : __('proposal.sections.version', ['no' => $version?->version_no]);

        if ($version === null) {
            return Section::make($heading)
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->compact()
                ->components([Text::make(__('proposal.steps.no_version'))->color('gray')]);
        }

        $entry = static fn (string $name, string $label, string $state, Heroicon $icon, string $color = 'gray'): TextEntry => TextEntry::make($prefix.'_'.$name)
            ->label($label)
            ->state($state)
            ->icon($icon)
            ->iconColor($color);

        // D-186: duzenlemedeki Teklif bilgileri gibi kapsam toplam satisi da burada
        // (marj zaten kartta); kapsam bolumlerinde ayri toplam karti yok.
        $scopeSales = SchemaReadiness::hasBatch('B43') && $version->scopes->isNotEmpty()
            ? [$entry('scope_total_sales', __('proposal_version.fields.scope_total_sales'), Money::format(ProposalVersionScopeService::totalSales($version->scopes), $version->currency_code), Heroicon::OutlinedCalculator, 'success')]
            : [];

        return Section::make($heading)
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->compact()
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->components([
                        TextEntry::make($prefix.'_status')
                            ->label(__('proposal_version.fields.status'))
                            ->state($version->status?->getLabel() ?? '-')
                            ->badge()
                            ->color($version->status?->getColor() ?? 'gray'),
                        $entry('total_price', __('proposal_version.fields.total_price'), self::money($version), Heroicon::OutlinedBanknotes, 'success'),
                        ...$scopeSales,
                        $entry('margin_pct', __('proposal_version.fields.margin_pct'), $version->margin_pct === null ? '-' : Number::format((float) $version->margin_pct, precision: 2, locale: 'tr').' %', Heroicon::OutlinedReceiptPercent),
                        $entry('validity_until', __('proposal_version.fields.validity_until'), $version->validity_until?->format('d.m.Y') ?? '-', Heroicon::OutlinedCalendarDays),
                        $entry('is_critical_route', __('proposal_version.fields.is_critical_route'), $version->is_critical_route ? __('export.values.yes') : __('export.values.no'), Heroicon::OutlinedExclamationTriangle, 'warning'),
                        $entry('preparer', __('proposal_version.fields.preparer'), (string) ($version->preparer?->full_name ?? '-'), Heroicon::OutlinedUser),
                        $entry('submitted_at', __('proposal_version.fields.submitted_at'), $version->submitted_at?->timezone(DisplayTime::zone())->format('d.m.Y H:i') ?? '-', Heroicon::OutlinedPaperAirplane),
                        $entry('created_at', __('proposal_version.fields.created_at'), $version->created_at?->timezone(DisplayTime::zone())->format('d.m.Y H:i') ?? '-', Heroicon::OutlinedClock),
                        $entry('summary', __('proposal_version.fields.summary'), filled($version->summary) ? (string) $version->summary : '-', Heroicon::OutlinedDocumentText)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * "Surumler" dugmesi (D-158, 5 Ekim 2026 kullanici talimati: "proposal-versions
     * kismini ortadan kaldirabiliriz ... surumler arasi gecis dropdown action buton
     * ile ... hangisini secersek aktif teklif disinda bir modal icerisinde teklifin
     * o surume gore ilgili tum bilgileri kart / schema yapilariyla ... compact
     * gostersin, belgeler okunsun / indirilebilsin; modal kapatildiginda aktif
     * teklif bilgisi sabit kalsin").
     *
     * Her surum bir menu satiridir (en yenisi ustte); guncel surum sayfada
     * oldugu icin pasiftir. Secilen surum pencerede: surum karti, proje kapsami
     * ve belgeler; salt okunur.
     */
    public function versionsAction(Proposal $proposal): ?ActionGroup
    {
        $versions = $proposal->versions()->orderByDesc('version_no')->get();

        if ($versions->isEmpty()) {
            return null;
        }

        $actions = [];

        foreach ($versions as $version) {
            /** @var ProposalVersion $version */
            $isCurrent = (int) $version->getKey() === (int) $proposal->current_version_id;
            $date = $version->created_at?->timezone(DisplayTime::zone())->format('d.m.Y') ?? '-';
            $status = (string) ($version->status?->getLabel() ?? '-');

            $actions[] = Action::make('version_'.$version->getKey())
                ->label(__($isCurrent ? 'proposal.versions.current' : 'proposal.versions.item', ['no' => $version->version_no, 'status' => $status, 'date' => $date]))
                ->icon($isCurrent ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedDocumentDuplicate)
                ->color($isCurrent ? 'success' : 'gray')
                ->disabled($isCurrent)
                ->modalHeading(__('proposal.sections.version', ['no' => $version->version_no]).' · '.$proposal->proposal_no)
                ->modalDescription(__('proposal.versions.modal_description', ['status' => $status, 'date' => $date]))
                ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
                ->modalWidth(Width::SixExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('proposal.versions.close'))
                ->schema(fn (): array => $this->versionModalComponents($proposal, $version));
        }

        return ActionGroup::make($actions)
            ->label(__('proposal.versions.button'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color(ActionColors::VIEW)
            // "Surum 1 · Yerini aldi · 05.10.2026" kesilmeden sigsin.
            ->dropdownWidth(Width::Small)
            ->button();
    }

    /**
     * Surum penceresinin icerigi: surum karti, kapsam ve belgeler (salt okunur).
     *
     * @return list<Component>
     */
    private function versionModalComponents(Proposal $proposal, ProposalVersion $version): array
    {
        $version->loadMissing([
            'preparer',
            'scopes.scopeDocument.revisions.files.fileObject',
            'scopes.scopeDocumentRevision.files.fileObject',
            'documents.documentRevision.document',
            'documents.documentRevision.files.fileObject',
        ]);

        $prefix = 'v'.$version->getKey();

        // D-186: kapsam, teklif sayfasi ve duzenleme ekraniyla ayni bolumlerle.
        return array_values(array_filter([
            $this->versionCard($proposal, $version, $prefix.'_version'),
            SchemaReadiness::hasBatch('B43') ? app(ProposalScopeSchema::class)->recordGrid($version, $prefix.'_scope') : null,
            app(ProposalFilesSchema::class)->versionCard($version, $prefix.'_document'),
        ]));
    }

    /**
     * "Yeni teklif surumu" dugmesi (D-186, 9 Ekim 2026 kullanici karari:
     * "Komple teklifte yeni surum secenegi olacak ... bu hamle teklif surumunu 2
     * yapacaktir"). Teklif detayinda ve duzenleme ekraninin basliginda; duzenleme
     * formunun aynisi olan NewProposalVersion sayfasini acar. Taslak teklifte,
     * surumu olmayan teklifte ya da guncelleme yetkisi yoksa gorunmez.
     */
    public function newVersionAction(Proposal $proposal): ?Action
    {
        if (! SchemaReadiness::hasBatch('B43') || $proposal->current_version_id === null) {
            return null;
        }

        return Action::make('new_proposal_version')
            ->label(__('proposal.new_version.action'))
            ->tooltip(__('proposal.new_version.tooltip', ['no' => (int) $proposal->versions()->max('version_no') + 1]))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color(ActionColors::CREATE)
            ->url(ProposalResource::getUrl('new-version', ['record' => $proposal]))
            ->visible(fn (): bool => ! (bool) $proposal->getAttribute('is_draft') && Gate::allows('update', $proposal));
    }

    private static function versionLine(ProposalVersion $version): string
    {
        return __('proposal.steps.version', ['no' => $version->version_no, 'status' => (string) ($version->status?->getLabel() ?? '-')]);
    }

    private static function money(?ProposalVersion $version): string
    {
        // D-180: tutar + para birimi simgesi (Money::format).
        return Money::format($version?->total_price, $version?->currency_code);
    }
}
