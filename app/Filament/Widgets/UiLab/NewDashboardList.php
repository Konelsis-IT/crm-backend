<?php

declare(strict_types=1);

namespace App\Filament\Widgets\UiLab;

use App\Filament\Pages\UiNewDashboard;
use App\Filament\Support\CodeLine;
use App\Enums\Document\RevisionStatus;
use App\Filament\Support\RecordLinks;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderDeadline;
use App\Models\Acquisition\TenderNotice;
use App\Models\Approval\ApprovalRequest;
use App\Models\Document\Document;
use App\Models\Party\MeetingPlan;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Query\Ui\NewDashboardQueries;
use App\Support\DisplayTime;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * UI Deneme > Yeni pano taslagi (D-207): panonun butun listeleri tek widget;
 * hangi liste oldugu $kind ile secilir. Satirlar salt okunur dizi kayittir,
 * satira tiklamak kaydin detayini acar. Kod basligin altinda (D-201).
 */
class NewDashboardList extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    public string $kind = 'to_submit';

    /** Sutun listeleri (gun, yonetici, satin alma) en fazla bu kadar satir gosterir. */
    private const COLUMN_LIMIT = 5;

    /** Hangi listede hangi sutunlar. */
    private const COLUMNS = [
        'to_submit' => ['grade', 'title', 'party', 'owner', 'days', 'amount'],
        'awaiting' => ['title', 'party', 'when', 'days', 'amount'],
        'concluded' => ['badge', 'title', 'party', 'when', 'amount'],
        'tenders' => ['title', 'party', 'when', 'days', 'badge'],
        'standard_docs' => ['title', 'party', 'badge', 'when'],
    ];

    public static function canView(): bool
    {
        return UiNewDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        $compact = ! array_key_exists($this->kind, self::COLUMNS);
        // Sutun listelerinde durum rozeti yalniz satirlarda rozet varsa (gun listelerinde yok).
        $columns = self::COLUMNS[$this->kind]
            ?? (collect($this->allRows())->contains(fn (array $row): bool => filled($row['badge'] ?? null)) ? ['title', 'badge'] : ['title']);

        return $table
            ->heading($compact ? fn (): string => $this->heading() : null)
            ->records(fn (): array => $this->rows())
            ->paginated($compact ? false : [10, 25])
            ->recordUrl(fn (array $record): ?string => $record['url'] ?? null)
            ->emptyStateHeading(__('new_dashboard.empty.'.$this->kind))
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->columns(array_values(array_filter([
                in_array('grade', $columns, true) ? TextColumn::make('grade')
                    ->label(__('new_dashboard.columns.grade'))
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '–' : __('new_dashboard.values.grade', ['grade' => $state]))
                    ->color(fn (?int $state): string => match ($state) {
                        1 => 'warning',
                        2 => 'info',
                        default => 'gray',
                    })
                    ->placeholder('–') : null,
                in_array('badge', $columns, true) && ! $compact ? $this->badgeColumn() : null,
                TextColumn::make('title')
                    ->label(__('new_dashboard.columns.'.$this->kind))
                    ->weight('medium')
                    ->wrap()
                    ->icon(fn (array $record): mixed => $record['icon'] ?? null)
                    ->iconColor('gray')
                    ->description(fn (array $record): ?string => $record['line'] ?? null)
                    ->extraAttributes(['class' => CodeLine::CSS_CLASS]),
                in_array('party', $columns, true) ? TextColumn::make('party')
                    ->label(__('new_dashboard.columns.party_'.$this->kind))
                    ->placeholder('–')
                    ->limit(30) : null,
                in_array('owner', $columns, true) ? TextColumn::make('owner')
                    ->label(__('new_dashboard.columns.owner'))
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->placeholder('–') : null,
                in_array('when', $columns, true) ? TextColumn::make('when')
                    ->label(__('new_dashboard.columns.when_'.$this->kind))
                    ->placeholder('–') : null,
                in_array('days', $columns, true) ? TextColumn::make('days')
                    ->label(__('new_dashboard.columns.days_'.$this->kind))
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : __('new_dashboard.values.days', ['days' => $state]))
                    ->color(fn (?int $state): string => $this->kind === 'tenders'
                        ? ($state !== null && $state <= 3 ? 'warning' : 'gray')
                        : ($state !== null && $state >= 30 ? 'warning' : 'gray'))
                    ->placeholder('–') : null,
                in_array('amount', $columns, true) ? TextColumn::make('amount')
                    ->label(__('new_dashboard.columns.amount'))
                    ->alignEnd()
                    ->placeholder('–') : null,
                in_array('badge', $columns, true) && $compact ? $this->badgeColumn() : null,
            ])));
    }

    private function badgeColumn(): TextColumn
    {
        return TextColumn::make('badge')
            ->label(__('new_dashboard.columns.status'))
            ->badge()
            ->color(fn (array $record): string => $record['badge_color'] ?? 'gray')
            ->placeholder('–');
    }

    /** Sutun listesinin basligi: ad ve kayit sayisi ("Bugun · 3"). */
    private function heading(): string
    {
        return __('new_dashboard.headings.'.$this->kind).' · '.count($this->allRows());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = $this->allRows();

        return array_key_exists($this->kind, self::COLUMNS) ? $rows : array_slice($rows, 0, self::COLUMN_LIMIT, true);
    }

    /** @var array<string, array<string, mixed>>|null */
    private ?array $rowCache = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    private function allRows(): array
    {
        if ($this->rowCache !== null) {
            return $this->rowCache;
        }

        $queries = app(NewDashboardQueries::class);

        $rows = match ($this->kind) {
            'to_submit' => $queries->proposalsToSubmit()->map(fn (Proposal $p): array => $this->proposalRow($p, 'created')),
            'awaiting' => $queries->proposalsAwaitingResult()->map(fn (Proposal $p): array => $this->proposalRow($p, 'submitted')),
            'concluded' => $queries->proposalsConcluded()->map(fn (Proposal $p): array => $this->proposalRow($p, 'concluded')),
            'tenders' => $queries->openTenders()->map(fn (TenderNotice $t): array => $this->tenderRow($t)),
            'standard_docs' => $queries->standardDocuments()->map(fn (Document $d): array => $this->documentRow($d)),
            'exec_cases' => $queries->casesInOfferPreparation()->map(fn (BusinessCase $c): array => $this->caseRow($c)),
            'exec_offers' => $queries->proposalsToSubmit()->map(fn (Proposal $p): array => $this->proposalRow($p, 'created')),
            'exec_approvals' => $queries->openApprovals()->map(fn (ApprovalRequest $a): array => $this->approvalRow($a)),
            'exec_purchasing', 'pur_entry' => $queries->openPurchaseRequests()->map(fn (PurchaseRequest $r): array => $this->requestRow($r))
                ->concat($queries->needsInEntry()->map(fn (PurchaseNeed $n): array => $this->needRow($n))),
            'pur_projects' => $queries->projectsWithOpenPurchasing()->map(fn (array $row): array => $this->projectRow($row)),
            'pur_orders' => $queries->pendingOrders()->map(fn (PurchaseOrder $o): array => $this->orderRow($o, false)),
            'pur_production' => $queries->ordersInProduction()->map(fn (PurchaseOrder $o): array => $this->orderRow($o, true)),
            default => collect(),
        };

        return $this->rowCache = $rows->values()->mapWithKeys(fn (array $row, int $i): array => [$this->kind.'-'.$i => $row])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalRow(Proposal $proposal, string $mode): array
    {
        $case = $proposal->businessCase;
        $version = $proposal->currentVersion;
        $since = match ($mode) {
            'submitted' => $version?->submitted_at ?? $proposal->created_at,
            'concluded' => $proposal->updated_at,
            default => $proposal->created_at,
        };
        $won = $proposal->currentOfferStatus()?->value === 'approved';

        return [
            'title' => (string) ($proposal->title ?? $proposal->proposal_no),
            'line' => implode(CodeLine::SEPARATOR, array_filter([$proposal->proposal_no, $case?->caseCode()?->formatted_code])),
            'grade' => NewDashboardQueries::grade($case?->heat_score),
            'party' => $case?->primaryParty?->display_name,
            'owner' => $proposal->owner?->full_name,
            'when' => $since instanceof Carbon ? DisplayTime::format($since, 'd.m.Y') : null,
            'days' => $since instanceof Carbon ? (int) $since->diffInDays(Carbon::now()) : null,
            'amount' => $version?->total_price !== null ? Money::format($version->total_price, $version->currency_code) : null,
            'badge' => $mode === 'concluded' ? __($won ? 'new_dashboard.values.won' : 'new_dashboard.values.lost') : null,
            'badge_color' => $won ? 'success' : 'gray',
            'url' => RecordLinks::detailUrl($proposal, checkRecord: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenderRow(TenderNotice $notice): array
    {
        $deadline = NewDashboardQueries::nextDeadline($notice);
        $due = $deadline?->local_due_date;
        $left = $due instanceof Carbon ? (int) DisplayTime::today()->diffInDays($due, false) : null;

        return [
            'title' => (string) $notice->title,
            'line' => $notice->external_notice_id,
            'party' => $notice->issuerParty?->display_name,
            'when' => $due instanceof Carbon ? $due->format('d.m.Y') : null,
            'days' => $left !== null && $left >= 0 ? $left : null,
            'badge' => $deadline?->deadline_type?->getLabel(),
            'badge_color' => 'info',
            'url' => RecordLinks::detailUrl($notice, checkRecord: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRow(Document $document): array
    {
        // Onayli: guncel revizyon onaylanmis ya da yayimlanmis (notlar: "dogru mu yanlis mi onaylanacak").
        $issued = in_array($document->currentRevision?->status, [RevisionStatus::Approved, RevisionStatus::Issued], true);

        return [
            'title' => (string) $document->title,
            'line' => $document->document_no,
            'party' => $document->documentType?->name,
            'badge' => __($issued ? 'new_dashboard.values.approved' : 'new_dashboard.values.awaiting_approval'),
            'badge_color' => $issued ? 'success' : 'warning',
            'when' => $document->updated_at instanceof Carbon ? DisplayTime::format($document->updated_at, 'd.m.Y') : null,
            'url' => RecordLinks::detailUrl($document, checkRecord: false),
        ];
    }

    /**
     * Dun / bugun / yarin kutusunun satirlari (sayfada sade liste olarak cizilir).
     *
     * @param  'yesterday'|'today'|'tomorrow'  $bucket
     * @return list<array<string, mixed>>
     */
    public static function dayRows(string $bucket): array
    {
        return array_map(self::dayRow(...), app(NewDashboardQueries::class)->days()[$bucket] ?? []);
    }

    /**
     * @param  array{kind: string, at: mixed, model: mixed}  $item
     * @return array<string, mixed>
     */
    private static function dayRow(array $item): array
    {
        $model = $item['model'];
        $at = $item['at'] instanceof Carbon ? $item['at'] : null;
        $time = $at !== null && $at->format('H:i:s') !== '00:00:00' ? DisplayTime::format($at, 'H:i') : null;

        [$title, $icon, $url, $code] = match ($item['kind']) {
            'proposal_submitted', 'proposal_created' => [(string) $model->title, Heroicon::OutlinedClipboardDocumentList, RecordLinks::detailUrl($model, checkRecord: false), $model->proposal_no],
            'tender_deadline' => [(string) ($model->version?->notice?->title ?? '-'), Heroicon::OutlinedMegaphone, $model->version?->notice instanceof Model ? RecordLinks::detailUrl($model->version->notice, checkRecord: false) : null, null],
            'meeting' => [(string) ($model->party?->display_name ?? $model->subject ?? '-'), Heroicon::OutlinedCalendarDays, RecordLinks::detailUrl($model, checkRecord: false), null],
            'case_created' => [(string) $model->title, Heroicon::OutlinedBriefcase, RecordLinks::detailUrl($model, checkRecord: false), $model->caseCode()?->formatted_code],
            'delivery' => [(string) ($model->supplierLabel() ?? $model->getAttribute('order_no')), Heroicon::OutlinedTruck, RecordLinks::detailUrl($model, checkRecord: false), $model->getAttribute('order_no')],
            default => ['-', null, null, null],
        };

        return [
            'title' => $title,
            'line' => implode(CodeLine::SEPARATOR, array_filter([__('new_dashboard.day_kinds.'.$item['kind']), $code, $time])),
            'icon' => $icon,
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caseRow(BusinessCase $case): array
    {
        $days = $case->updated_at instanceof Carbon ? (int) $case->updated_at->diffInDays(Carbon::now()) : null;

        return [
            'title' => (string) $case->title,
            'line' => implode(CodeLine::SEPARATOR, array_filter([
                $case->caseCode()?->formatted_code,
                $case->primaryParty?->display_name,
                $days !== null ? __('new_dashboard.values.waiting', ['days' => $days]) : null,
            ])),
            'url' => RecordLinks::detailUrl($case, checkRecord: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalRow(ApprovalRequest $request): array
    {
        $days = $request->created_at instanceof Carbon ? (int) $request->created_at->diffInDays(Carbon::now()) : null;

        return [
            'title' => (string) $request->subject_label,
            'line' => implode(CodeLine::SEPARATOR, array_filter([
                $request->requester?->full_name,
                $days !== null ? __('new_dashboard.values.waiting', ['days' => $days]) : null,
            ])),
            'url' => RecordLinks::detailUrl($request, checkRecord: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestRow(PurchaseRequest $request): array
    {
        return [
            'title' => (string) $request->getAttribute('request_no'),
            'line' => implode(CodeLine::SEPARATOR, array_filter([
                $request->requestedBy?->full_name,
                $request->project?->getAttribute('display_name'),
            ])),
            'badge' => $request->status?->getLabel(),
            'badge_color' => 'info',
            'url' => RecordLinks::detailUrl($request, checkRecord: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function needRow(PurchaseNeed $need): array
    {
        return [
            'title' => (string) $need->getAttribute('title'),
            'line' => implode(CodeLine::SEPARATOR, array_filter([
                $need->purchaseRequest?->getAttribute('request_no'),
                $need->project?->getAttribute('display_name'),
            ])),
            'badge' => $need->status?->getLabel(),
            'badge_color' => 'warning',
            'url' => RecordLinks::detailUrl($need, checkRecord: false),
        ];
    }

    /**
     * @param  array{project: mixed, open: int, ordered: int}  $row
     * @return array<string, mixed>
     */
    private function projectRow(array $row): array
    {
        $project = $row['project'];

        return [
            'title' => $project instanceof Model ? (string) $project->getAttribute('display_name') : '-',
            'line' => __('new_dashboard.values.project_needs', ['open' => $row['open'], 'ordered' => $row['ordered']]),
            'url' => $project instanceof Model ? RecordLinks::detailUrl($project, checkRecord: false) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(PurchaseOrder $order, bool $production): array
    {
        $expected = $order->getAttribute('expected_delivery_on');

        return [
            'title' => (string) ($order->supplierLabel() ?? $order->getAttribute('order_no')),
            'line' => implode(CodeLine::SEPARATOR, array_filter([
                $order->getAttribute('order_no'),
                $production && $expected instanceof Carbon ? __('new_dashboard.values.expected', ['date' => $expected->format('d.m.Y')]) : null,
                $order->getAttribute('total_amount') !== null ? Money::format($order->getAttribute('total_amount'), $order->getAttribute('currency_code')) : null,
            ])),
            'badge' => $order->status?->getLabel(),
            'badge_color' => $production ? 'info' : 'warning',
            'url' => RecordLinks::detailUrl($order, checkRecord: false),
        ];
    }
}
