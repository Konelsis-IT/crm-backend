<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\TenderNoticeStatus;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\TenderNotice;
use App\Query\Acquisition\TenderQueries;
use App\Query\Document\DocumentQueries;
use App\Query\Party\PartyQueries;
use App\Support\DisplayTime;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Ihale adimi ve ihale kartlari (B43, D-155; 5 Ekim 2026 kullanici talimati:
 * "Potansiyel isten bir adim oncesi de ihale olustur ekrani olacak ... potansiyel
 * is secimine gerek yok ... olustur - duzenle ekranlari ayni ekran duzeninde.
 * Ihale detayi da kart yapisiyla").
 *
 * - fields(): ilan alanlari. Ihale ekraninda kokte, potansiyel is sihirbazinda
 *   `tender.*` altinda (ayni formda potansiyel isin "title" alani da vardir).
 * - choiceStep(): potansiyel is sihirbazinin 1. adimi: ihale yok / mevcut
 *   ihaleyi bagla / yeni ihale ac.
 * - recordSteps... ihale sayfalarinin adimlari BusinessCaseWizard'dadir; bu
 *   sinif kartlari verir (headerCard, detailsCard, summaryCard).
 */
final class TenderSchema
{
    public const MODE_NONE = 'none';

    public const MODE_EXISTING = 'existing';

    public const MODE_NEW = 'new';

    /**
     * Ilan alanlari. $prefix bossa kokte (ihale ekrani), degilse "{prefix}.alan".
     * $required: zorunlu alanlarin kosulu (sihirbazda yalniz "Yeni ihale"de).
     *
     * @param  (Closure(Get): bool)|bool  $required
     * @return list<Component>
     */
    public function fields(string $prefix = '', Closure|bool $required = true, bool $editing = false): array
    {
        $p = $prefix === '' ? '' : $prefix.'.';

        return [
            Select::make($p.'tender_source_id')
                ->label(__('tender_notice.fields.tender_source'))
                ->options(fn (): array => app(TenderQueries::class)->sourceOptions())
                ->searchable()
                ->required($required)
                ->native(false),
            TextInput::make($p.'title')
                ->label(__('tender_notice.fields.title'))
                ->required($required)
                ->maxLength(255),
            TextInput::make($p.'external_notice_id')
                ->label(__('tender_notice.fields.external_notice'))
                ->maxLength(100),
            Select::make($p.'issuer_party_id')
                ->label(__('tender_notice.fields.issuer_party'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => app(PartyQueries::class)->searchOptions($search))
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? app(PartyQueries::class)->displayName((int) $value) : null)
                ->native(false),
            TextInput::make($p.'notice_url')
                ->label(__('tender_notice.fields.notice_url'))
                ->maxLength(2048),
            Select::make($p.'status')
                ->label(__('tender_notice.fields.status'))
                ->options(TenderNoticeStatus::class)
                ->default(TenderNoticeStatus::Captured->value)
                ->required($required)
                ->native(false),
            Textarea::make($p.'summary')
                ->label(__('tender_notice.fields.summary'))
                ->visible(! $editing)
                ->columnSpan(FieldGrid::LONG),
            DatePicker::make($p.'published_on')
                ->label(__('tender_notice.fields.published_on'))
                ->displayFormat('d.m.Y')
                ->visible(! $editing),
            Select::make($p.'source_document_revision_id')
                ->label(__('tender_notice.fields.source_document_revision'))
                ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                ->searchable()
                ->native(false)
                ->visible(! $editing),
        ];
    }

    /**
     * Alanlar bolumler halinde (ilan kimligi, yayin ve durum, ozet).
     *
     * @param  (Closure(Get): bool)|bool  $required
     * @param  (Closure(Get): bool)|null  $visible
     * @return list<Component>
     */
    public function sections(string $prefix = '', Closure|bool $required = true, bool $editing = false, ?Closure $visible = null): array
    {
        $p = $prefix === '' ? '' : $prefix.'.';
        $visible ??= static fn (): bool => true;

        return FieldGrid::group($this->fields($prefix, $required, $editing), [
            'identity' => ['label' => __('tender_notice.sections.identity'), 'icon' => Heroicon::OutlinedMegaphone, 'fields' => [$p.'tender_source_id', $p.'title', $p.'external_notice_id', $p.'issuer_party_id'], 'visible' => $visible],
            'publication' => ['label' => __('tender_notice.sections.publication'), 'icon' => Heroicon::OutlinedGlobeAlt, 'fields' => [$p.'notice_url', $p.'status', $p.'published_on', $p.'source_document_revision_id'], 'visible' => $visible],
            'summary' => ['label' => __('tender_notice.sections.summary'), 'icon' => Heroicon::OutlinedDocumentText, 'fields' => [$p.'summary'], 'visible' => fn (Get $get): bool => ! $editing && $visible($get)],
        ]);
    }

    /**
     * Potansiyel is sihirbazinin ihale adimi: ihale yok / mevcut ihale / yeni
     * ihale. Duzenlemede bagli ihaleler ustte kart olarak durur; adim yeni
     * baglanti ya da yeni ihale icindir. $locked: ihaleden acilan potansiyel
     * iste secim sabittir (?ihale=).
     */
    public function choiceStep(?BusinessCase $case = null, bool $locked = false): Step
    {
        $mode = static fn (Get $get): string => (string) ($get('tender_mode') ?? self::MODE_NONE);
        $linked = $case !== null ? app(TenderQueries::class)->forCase((int) $case->getKey()) : collect();

        return Step::make(__('business_case.wizard.tender'))
            ->id(BusinessCaseWizard::STEP_TENDER)
            ->icon(Heroicon::OutlinedMegaphone)
            ->completedIcon(Heroicon::OutlinedMegaphone)
            ->columns(1)
            ->schema([
                ...$linked->map(fn (TenderNotice $notice): Component => $this->summaryCard($notice))->all(),
                Section::make($linked->isEmpty() ? __('business_case.sections.tender') : __('business_case.sections.tender_more'))
                    ->key('tender-choice')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->columns(FieldGrid::COLUMNS)
                    ->components([
                        ToggleButtons::make('tender_mode')
                            ->label(__('business_case.fields.tender_mode'))
                            ->options([
                                self::MODE_NONE => $linked->isEmpty() ? __('business_case.values.tender_none') : __('business_case.values.tender_keep'),
                                self::MODE_EXISTING => __('business_case.values.tender_existing'),
                                self::MODE_NEW => __('business_case.values.tender_new'),
                            ])
                            ->icons([
                                self::MODE_NONE => Heroicon::OutlinedMinusCircle,
                                self::MODE_EXISTING => Heroicon::OutlinedLink,
                                self::MODE_NEW => Heroicon::OutlinedPlusCircle,
                            ])
                            ->default(self::MODE_NONE)
                            ->inline()
                            ->live()
                            ->disabled($locked)
                            ->dehydrated()
                            // Uc dugme yarim satira sigar (D-157: tam satir yok).
                            ->columnSpan(FieldGrid::HALF),
                        Select::make('tender_notice_id')
                            ->label(__('business_case.fields.tender_notice'))
                            ->helperText(__('business_case.help.tender_existing'))
                            ->options(fn (): array => app(TenderQueries::class)->linkableOptions($case?->getKey() === null ? null : (int) $case->getKey()))
                            ->searchable()
                            ->required(fn (Get $get): bool => $mode($get) === self::MODE_EXISTING)
                            ->visible(fn (Get $get): bool => $mode($get) === self::MODE_EXISTING)
                            ->disabled($locked)
                            ->dehydrated()
                            ->live()
                            ->native(false)
                            ->columnSpan(FieldGrid::HALF),
                        TextEntry::make('tender_notice_summary')
                            ->hiddenLabel()
                            ->state(fn (Get $get): string => $this->summaryLine(app(TenderQueries::class)->forSummary(is_numeric($get('tender_notice_id')) ? (int) $get('tender_notice_id') : null)))
                            ->visible(fn (Get $get): bool => $mode($get) === self::MODE_EXISTING && filled($get('tender_notice_id')))
                            ->icon(Heroicon::OutlinedMegaphone)
                            ->iconColor('primary')
                            ->dehydrated(false)
                            ->columnSpan(FieldGrid::HALF),
                    ]),
                ...$this->sections('tender', fn (Get $get): bool => $mode($get) === self::MODE_NEW, visible: fn (Get $get): bool => $mode($get) === self::MODE_NEW),
            ]);
    }

    /**
     * Teklif sihirbazinin ihale adimi (yalniz okunur): secilen potansiyel isin ihaleleri.
     *
     * @param  Closure(Get): (int|null)  $caseId
     */
    public function infoStep(Closure $caseId): Step
    {
        return Step::make(__('business_case.wizard.tender'))
            ->id(BusinessCaseWizard::STEP_TENDER)
            ->icon(Heroicon::OutlinedMegaphone)
            ->completedIcon(Heroicon::OutlinedMegaphone)
            ->columns(1)
            ->schema([
                TextEntry::make('case_tenders')
                    ->label(__('business_case.sections.tender'))
                    ->state(function (Get $get) use ($caseId): array {
                        $id = $caseId($get);
                        $lines = $id === null ? [] : app(TenderQueries::class)->forCase($id)->map(fn (TenderNotice $notice): string => $this->summaryLine($notice))->all();

                        return $lines === [] ? [__('business_case.help.no_tender')] : $lines;
                    })
                    ->listWithLineBreaks()
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->iconColor('gray')
                    ->dehydrated(false),
            ]);
    }

    /** Ihale ozet karti (sihirbaz adimlari): baslik, durum, kaynak, ilani veren, baglanti. */
    public function summaryCard(TenderNotice $notice): Component
    {
        $status = $notice->status;

        return Section::make(trim((filled($notice->external_notice_id) ? $notice->external_notice_id.' · ' : '').$notice->title))
            ->key('tender-summary-'.$notice->getKey())
            ->icon(Heroicon::OutlinedMegaphone)
            ->compact()
            ->extraAttributes(['class' => 'konelsis-card konelsis-card-row konelsis-card-detail'])
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])
                    ->extraAttributes(['class' => 'konelsis-card-entries'])
                    ->components([
                        TextEntry::make('tender_summary_status_'.$notice->getKey())
                            ->label(__('tender_notice.fields.status'))
                            ->state($status?->getLabel() ?? '-')
                            ->badge()
                            ->color($status?->getColor() ?? 'gray'),
                        TextEntry::make('tender_summary_source_'.$notice->getKey())
                            ->label(__('tender_notice.fields.tender_source'))
                            ->state((string) ($notice->source?->name_tr ?? '-'))
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->iconColor('gray'),
                        TextEntry::make('tender_summary_issuer_'.$notice->getKey())
                            ->label(__('tender_notice.fields.issuer_party'))
                            ->state((string) ($notice->issuerParty?->display_name ?? '-'))
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->iconColor('gray'),
                        TextEntry::make('tender_summary_open_'.$notice->getKey())
                            ->label(__('tender_notice.label'))
                            ->state(__('tender_notice.actions.open'))
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->iconColor('primary')
                            ->color('primary')
                            ->url(Gate::allows('view', $notice) ? TenderNoticeResource::getUrl('view', ['record' => $notice]) : null),
                    ]),
            ])
            ->columnSpanFull();
    }

    /** Ihale karti (detay sayfasi): baslik, rozetler ve baglantili alanlar. */
    public function headerCard(TenderNotice $notice): Component
    {
        $status = $notice->status;
        $case = $notice->businessCase;
        $issuer = $notice->issuerParty;

        $badges = [
            Text::make($status?->getLabel() ?? '-')->badge()->color($status?->getColor() ?? 'gray'),
        ];

        if (filled($notice->external_notice_id)) {
            $badges[] = Text::make((string) $notice->external_notice_id)->badge()->color('gray')->icon(Heroicon::OutlinedHashtag);
        }

        if ((bool) $notice->getAttribute('is_draft')) {
            $badges[] = Text::make(__('app.values.draft'))->badge()->color('gray')->icon(Heroicon::OutlinedPencilSquare);
        }

        return Section::make(__('tender_notice.sections.header'))
            ->icon(Heroicon::OutlinedMegaphone)
            ->compact()
            ->components([
                Text::make((string) $notice->title)->size(TextSize::Large)->weight(FontWeight::Bold),
                Flex::make($badges),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components([
                    TextEntry::make('issuer_party')
                        ->label(__('tender_notice.fields.issuer_party'))
                        ->state($issuer?->display_name ?? '-')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->iconColor('primary')
                        ->color($issuer !== null ? 'primary' : 'gray')
                        ->weight(FontWeight::SemiBold)
                        ->url($issuer !== null && Gate::allows('view', $issuer) ? PartyResource::getUrl('view', ['record' => $issuer]) : null),
                    TextEntry::make('business_case')
                        ->label(__('tender_notice.fields.business_case'))
                        ->state($case === null ? __('tender_notice.help.no_case') : trim(($case->caseCode()?->formatted_code ?? '').' · '.$case->title, ' ·'))
                        ->icon(Heroicon::OutlinedBriefcase)
                        ->iconColor($case !== null ? 'primary' : 'gray')
                        ->color($case !== null ? 'primary' : 'gray')
                        ->weight(FontWeight::SemiBold)
                        ->url($case !== null && Gate::allows('view', $case) ? BusinessCaseResource::getUrl('view', ['record' => $case]) : null),
                    TextEntry::make('tender_source')
                        ->label(__('tender_notice.fields.tender_source'))
                        ->state((string) ($notice->source?->name_tr ?? '-'))
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->iconColor('gray'),
                    TextEntry::make('notice_url')
                        ->label(__('tender_notice.fields.notice_url'))
                        ->state(filled($notice->notice_url) ? (string) $notice->notice_url : '-')
                        ->icon(Heroicon::OutlinedLink)
                        ->iconColor('gray')
                        ->color(filled($notice->notice_url) ? 'primary' : 'gray')
                        ->url(filled($notice->notice_url) ? (string) $notice->notice_url : null, shouldOpenInNewTab: true)
                        ->limit(48),
                    TextEntry::make('captured_at')
                        ->label(__('tender_notice.fields.captured_at'))
                        ->state(DisplayTime::format($notice->captured_at))
                        ->icon(Heroicon::OutlinedClock)
                        ->iconColor('gray'),
                ]),
            ]);
    }

    /** Ihale ayrintilari: guncel surumun ozeti, yayim tarihi, ilan dosyasi. */
    public function detailsCard(TenderNotice $notice): Component
    {
        $version = $notice->currentVersion;
        $revision = $version?->sourceDocumentRevision;
        $info = $revision !== null ? DocumentLine::info($revision->document, $revision) : null;

        return Section::make(__('tender_notice.sections.details'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->compact()
            ->components([
                Grid::make(['default' => 1, 'md' => 2])->components([
                    TextEntry::make('summary')
                        ->label(__('tender_notice.fields.summary'))
                        ->state(filled($version?->summary) ? (string) $version->summary : '-')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->iconColor('gray')
                        ->columnSpanFull(),
                    TextEntry::make('published_on')
                        ->label(__('tender_notice.fields.published_on'))
                        ->state($version?->published_on?->format('d.m.Y') ?? '-')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->iconColor('gray'),
                    TextEntry::make('current_version')
                        ->label(__('tender_notice.fields.current_version'))
                        ->state($version === null ? '-' : (string) $version->version_no)
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->iconColor('gray'),
                    TextEntry::make('source_document')
                        ->label(__('tender_notice.fields.source_document_revision'))
                        ->state(DocumentLine::text($info, withTitle: true))
                        ->icon(Heroicon::OutlinedDocumentArrowDown)
                        ->iconColor($info !== null ? 'primary' : 'gray')
                        ->color($info !== null ? 'primary' : 'gray')
                        ->url($info['url'] ?? null)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    /** Tek satir: "Harici no · Baslik · Durum · Ilani veren". */
    public function summaryLine(?TenderNotice $notice): string
    {
        if ($notice === null) {
            return '-';
        }

        return implode(' · ', array_filter([
            $notice->external_notice_id,
            $notice->title,
            $notice->status?->getLabel(),
            $notice->issuerParty?->display_name,
        ], static fn (mixed $part): bool => filled($part)));
    }
}
