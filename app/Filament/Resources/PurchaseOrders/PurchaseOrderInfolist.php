<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Support\PurchaseLines;
use App\Filament\Support\RecordLinks;
use App\Models\Approval\ApprovalRequest;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

/**
 * Siparis ayrintisi (D-192; kullanici: "Siparis durumunda ise toplu bir
 * siparis varsa 7. ekran goruntusune benzer bir arayuz"). Ekran goruntusu 7
 * duzeni: ustte genis siparis karti (tedarikci, teklif, proje, durum) ve
 * yaninda fiyat bilgileri; ortada kalem tablosu (aciklama, malzeme / hizmet,
 * ilgili talep / proje, miktar, birim fiyat, indirim, tutar, teslim alinan);
 * altta yan yana lojistik sureci (asamalar ve zamanlari), teslimat ve onay
 * kartlari. Filament bilesenleriyle (ozel arayuz kodu yok).
 */
final class PurchaseOrderInfolist
{
    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            Grid::make(['default' => 1, 'xl' => 3])->components([
                self::orderSection()->columnSpan(['default' => 1, 'xl' => 2]),
                self::pricesSection(),
            ]),
            self::linesSection(),
            Grid::make(['default' => 1, 'xl' => 3])->components([
                self::timelineSection(),
                self::deliverySection(),
                self::approvalSection(),
            ]),
        ];
    }

    private static function orderSection(): Section
    {
        return Section::make(__('purchase_order.sections.order'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
            ->components([
                TextEntry::make('order_no')
                    ->label(__('purchase_order.fields.order_no'))
                    ->weight(FontWeight::Bold),
                TextEntry::make('status')
                    ->label(__('purchase_order.fields.status'))
                    ->badge(),
                TextEntry::make('supplier_label')
                    ->label(__('purchase_order.fields.supplier'))
                    ->state(fn (PurchaseOrder $record): ?string => $record->supplierLabel() !== '' ? $record->supplierLabel() : null)
                    ->url(fn (PurchaseOrder $record): ?string => $record->supplier instanceof Party ? RecordLinks::detailUrl($record->supplier, checkRecord: false) : null)
                    ->icon(fn (PurchaseOrder $record): mixed => $record->supplier instanceof Party ? RecordLinks::iconFor($record->supplier) : null)
                    ->placeholder(__('purchase_order.values.no_supplier')),
                TextEntry::make('quotation.quotation_no')
                    ->label(__('purchase_order.fields.quotation'))
                    ->placeholder('-'),
                TextEntry::make('project.name')
                    ->label(__('purchase_order.fields.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseOrder $record): string => (string) ($record->project?->getAttribute('display_name') ?? $state))
                    ->placeholder('-'),
                TextEntry::make('currency_code')
                    ->label(__('purchase_order.fields.currency'))
                    ->formatStateUsing(fn (mixed $state): string => Money::label($state)),
                TextEntry::make('expected_delivery_on')
                    ->label(__('purchase_order.fields.expected_delivery_on'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseOrder $record): ?string => $record->isLate() ? 'danger' : null)
                    ->icon(fn (PurchaseOrder $record): ?Heroicon => $record->isLate() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->tooltip(fn (PurchaseOrder $record): ?string => $record->isLate() ? __('purchase_order.values.late') : null)
                    ->placeholder('-'),
                TextEntry::make('payment_terms')
                    ->label(__('purchase_order.fields.payment_terms'))
                    ->placeholder('-'),
                TextEntry::make('createdBy.full_name')
                    ->label(__('purchase_order.fields.created_by'))
                    ->placeholder(__('activity.system')),
                TextEntry::make('orderedBy.full_name')
                    ->label(__('purchase_order.fields.ordered_by'))
                    ->visible(fn (PurchaseOrder $record): bool => $record->getAttribute('ordered_by_personnel_id') !== null),
                TextEntry::make('completedBy.full_name')
                    ->label(__('purchase_order.fields.completed_by'))
                    ->visible(fn (PurchaseOrder $record): bool => $record->getAttribute('completed_by_personnel_id') !== null),
                TextEntry::make('archived_at')
                    ->label(__('purchase_order.fields.archived_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseOrder $record): bool => $record->isArchived()),
                TextEntry::make('note')
                    ->label(__('purchase_order.fields.note'))
                    ->placeholder('-'),
            ]);
    }

    private static function pricesSection(): Section
    {
        $money = static fn (PurchaseOrder $record, float $amount, bool $negative = false): string => ($negative && $amount > 0 ? '-' : '')
            .Money::format($amount, $record->getAttribute('currency_code'), Money::format(0, $record->getAttribute('currency_code')));

        return Section::make(__('purchase_quotation.sections.prices'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->components([
                TextEntry::make('price_subtotal')
                    ->label(__('purchase_order.fields.subtotal'))
                    ->state(fn (PurchaseOrder $record): string => $money($record, (float) $record->getAttribute('subtotal')))
                    ->inlineLabel()
                    ->alignEnd(),
                TextEntry::make('price_discount')
                    ->label(__('purchase_order.fields.discount_total'))
                    ->state(fn (PurchaseOrder $record): string => $money($record, (float) $record->getAttribute('discount_total'), true))
                    ->color('danger')
                    ->weight(FontWeight::SemiBold)
                    ->inlineLabel()
                    ->alignEnd(),
                TextEntry::make('price_total')
                    ->label(__('purchase_order.fields.total'))
                    ->state(fn (PurchaseOrder $record): string => $money($record, (float) $record->getAttribute('total_amount')))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->inlineLabel()
                    ->alignEnd()
                    ->extraEntryWrapperAttributes(['class' => 'kcp-sum-total']),
                TextEntry::make('price_try')
                    ->label(__('purchase_order.fields.total_try'))
                    ->state(fn (PurchaseOrder $record): string => Money::format($record->totalInTry(), 'TRY'))
                    ->visible(fn (PurchaseOrder $record): bool => strtoupper((string) $record->getAttribute('currency_code')) !== 'TRY')
                    ->inlineLabel()
                    ->alignEnd(),
            ]);
    }

    private static function linesSection(): Section
    {
        $currency = static fn (PurchaseOrderLine $record): mixed => $record->order?->getAttribute('currency_code');

        return Section::make(__('purchase_order.sections.lines'))
            ->icon(Heroicon::OutlinedListBullet)
            ->components([
                RepeatableEntry::make('lines')
                    // Gizli etiket ekran okuyucu icin de Turkce olsun ("Lines" okunuyordu).
                    ->label(__('purchase_order.sections.lines'))
                    ->hiddenLabel()
                    ->table([
                        TableColumn::make(__('purchase_order.line.no'))->width('3rem'),
                        TableColumn::make(__('purchase_order.line.description')),
                        TableColumn::make(__('purchase_order.line.catalog_item')),
                        TableColumn::make(__('purchase_order.line.source')),
                        TableColumn::make(__('purchase_order.line.quantity'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_order.line.unit_price'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_order.line.discount_rate'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_order.line.line_total'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_order.line.received_quantity'))->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('sort_order')
                            ->formatStateUsing(fn (mixed $state): string => (string) ((int) $state + 1)),
                        TextEntry::make('description')
                            ->formatStateUsing(fn (mixed $state, PurchaseOrderLine $record): string => trim((string) $state.(filled($record->getAttribute('brand_model')) ? ' · '.$record->getAttribute('brand_model') : '')))
                            ->wrap(),
                        TextEntry::make('catalogItem.name')
                            ->placeholder('-')
                            ->wrap(),
                        TextEntry::make('source')
                            ->state(fn (PurchaseOrderLine $record): ?string => self::sourceText($record->need))
                            ->url(fn (PurchaseOrderLine $record): ?string => $record->need instanceof PurchaseNeed ? RecordLinks::detailUrl($record->need) : null)
                            ->icon(fn (PurchaseOrderLine $record): mixed => $record->need instanceof PurchaseNeed ? RecordLinks::iconFor($record->need) : null)
                            ->placeholder('-')
                            ->wrap(),
                        TextEntry::make('quantity')
                            ->formatStateUsing(fn (mixed $state, PurchaseOrderLine $record): string => PurchaseLines::quantityText($state, app(UnitResolver::class)->display($record)))
                            ->alignEnd(),
                        TextEntry::make('unit_price')
                            ->formatStateUsing(fn (mixed $state, PurchaseOrderLine $record): string => Money::format($state, $currency($record), '-', 4))
                            ->alignEnd(),
                        TextEntry::make('discount_rate')
                            ->formatStateUsing(fn (mixed $state): string => (float) $state > 0 ? '%'.Money::number($state, 2) : '-')
                            ->alignEnd(),
                        TextEntry::make('line_total')
                            ->formatStateUsing(fn (mixed $state, PurchaseOrderLine $record): string => Money::format($state, $currency($record)))
                            ->weight(FontWeight::SemiBold)
                            ->alignEnd(),
                        TextEntry::make('received_quantity')
                            ->formatStateUsing(fn (mixed $state, PurchaseOrderLine $record): string => (float) $state > 0 ? PurchaseLines::quantityText($state, app(UnitResolver::class)->display($record)) : '-')
                            ->color(fn (PurchaseOrderLine $record): ?string => (float) $record->getAttribute('received_quantity') <= 0 ? null : ($record->isFullyReceived() ? 'success' : 'warning'))
                            ->icon(fn (PurchaseOrderLine $record): ?Heroicon => (float) $record->getAttribute('received_quantity') <= 0 ? null : ($record->isFullyReceived() ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle))
                            ->alignEnd(),
                    ]),
            ]);
    }

    /**
     * Lojistik asamalari (PurchaseOrderStatus::stages) ve zamanlari: gecilen
     * asama yesil, bulunulan asama vurgulu, bekleyen gri; onay gerekmeyen
     * sipariste onay asamalari "Gerekmedi".
     */
    private static function timelineSection(): Section
    {
        $entries = [];

        foreach (PurchaseOrderStatus::stages() as $stage) {
            $entries[] = TextEntry::make('stage_'.$stage->value)
                ->label(__('purchase_order.timeline.'.$stage->value))
                ->state(fn (PurchaseOrder $record): string => self::stageText($record, $stage))
                ->icon($stage->getIcon())
                ->iconColor(fn (PurchaseOrder $record): string => self::stageTone($record, $stage))
                ->color(fn (PurchaseOrder $record): ?string => self::stageTone($record, $stage) === 'gray' ? 'gray' : null)
                ->weight(fn (PurchaseOrder $record): ?FontWeight => $record->statusEnum() === $stage ? FontWeight::Bold : null)
                ->inlineLabel();
        }

        $entries[] = TextEntry::make('stage_cancelled')
            ->hiddenLabel()
            ->state(fn (PurchaseOrder $record): string => __('purchase_order.timeline.cancelled', ['date' => self::moment($record->getAttribute('cancelled_at')) ?? '-']))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (PurchaseOrder $record): bool => $record->statusEnum() === PurchaseOrderStatus::Cancelled);

        return Section::make(__('purchase_order.sections.timeline'))
            ->icon(Heroicon::OutlinedTruck)
            ->components($entries);
    }

    private static function deliverySection(): Section
    {
        return Section::make(__('purchase_order.sections.delivery'))
            ->icon(Heroicon::OutlinedMapPin)
            ->components([
                TextEntry::make('delivery_address')
                    ->label(__('purchase_order.fields.delivery_address'))
                    ->placeholder('-'),
                TextEntry::make('delivery_contact')
                    ->label(__('purchase_order.fields.delivery_contact'))
                    ->icon(Heroicon::OutlinedUser)
                    ->placeholder('-'),
                TextEntry::make('delivery_period')
                    ->label(__('purchase_order.fields.delivery_period'))
                    ->state(function (PurchaseOrder $record): ?string {
                        $from = $record->getAttribute('delivery_from')?->format('d.m.Y');
                        $until = $record->getAttribute('delivery_until')?->format('d.m.Y');

                        return $from === null && $until === null ? null : ($from ?? '…').' – '.($until ?? '…');
                    })
                    ->placeholder('-'),
                TextEntry::make('expected_delivery_on')
                    ->label(__('purchase_order.fields.expected_delivery_on'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseOrder $record): ?string => $record->isLate() ? 'danger' : null)
                    ->placeholder('-'),
                TextEntry::make('carrier')
                    ->label(__('purchase_order.fields.carrier'))
                    ->placeholder('-'),
                TextEntry::make('tracking_no')
                    ->label(__('purchase_order.fields.tracking_no'))
                    ->copyable()
                    ->placeholder('-'),
            ]);
    }

    /** Onay karti: gerekli mi, TL tutar, merciler, durum, eksik olan. */
    private static function approvalSection(): Section
    {
        $state = static fn (PurchaseOrder $record) => PurchaseOrderActions::approvalState($record);

        return Section::make(__('purchase_order.sections.approval'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->components([
                TextEntry::make('approval_required')
                    ->label(__('purchase_order.approval.required'))
                    ->state(fn (PurchaseOrder $record): string => $state($record)->required ? __('purchase_order.approval.values.yes_required') : __('purchase_order.approval.values.not_required'))
                    ->badge()
                    ->color(fn (PurchaseOrder $record): string => $state($record)->required ? 'warning' : 'success'),
                TextEntry::make('approval_progress')
                    ->label(__('purchase_order.approval.state'))
                    ->state(fn (PurchaseOrder $record): string => match (true) {
                        $state($record)->satisfied => __('purchase_order.approval.values.satisfied'),
                        $state($record)->pending => __('purchase_order.approval.values.pending'),
                        default => __('purchase_order.approval.values.not_requested'),
                    })
                    ->badge()
                    ->color(fn (PurchaseOrder $record): string => $state($record)->satisfied ? 'success' : 'warning')
                    ->visible(fn (PurchaseOrder $record): bool => $state($record)->required),
                TextEntry::make('approval_amount')
                    ->label(__('purchase_order.approval.amount_try'))
                    ->state(fn (PurchaseOrder $record): string => Money::format($state($record)->amountTry, 'TRY'))
                    ->weight(FontWeight::Bold)
                    ->visible(fn (PurchaseOrder $record): bool => $state($record)->required),
                TextEntry::make('approval_policy')
                    ->label(__('purchase_order.approval.policy'))
                    ->state(fn (PurchaseOrder $record): ?string => $state($record)->policyName)
                    ->placeholder('-')
                    ->visible(fn (PurchaseOrder $record): bool => $state($record)->required),
                TextEntry::make('approval_approvers')
                    ->label(__('purchase_order.approval.approvers'))
                    ->state(fn (PurchaseOrder $record): array => $state($record)->approvers)
                    ->listWithLineBreaks()
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->placeholder('-')
                    ->visible(fn (PurchaseOrder $record): bool => $state($record)->required),
                TextEntry::make('approvalRequest.status')
                    ->label(__('purchase_order.approval.request'))
                    ->badge()
                    ->visible(fn (PurchaseOrder $record): bool => $record->getAttribute('approval_request_id') !== null),
                TextEntry::make('approval_missing')
                    ->label(__('purchase_order.approval.missing'))
                    ->state(fn (PurchaseOrder $record): ?string => PurchaseOrderActions::missingText($state($record)))
                    ->color('danger')
                    ->visible(fn (PurchaseOrder $record): bool => PurchaseOrderActions::blocked($record)
                        && PurchaseOrderActions::missingText($state($record)) !== null),
            ]);
    }

    /** "SAT-2026-0003 · Proje adi" ya da kalemin projesi / talep adi. */
    private static function sourceText(?PurchaseNeed $need): ?string
    {
        if (! $need instanceof PurchaseNeed) {
            return null;
        }

        $parts = array_values(array_filter([
            $need->purchaseRequest?->getAttribute('request_no'),
            $need->project?->getAttribute('display_name'),
        ], static fn (mixed $part): bool => filled($part)));

        return $parts === [] ? (string) $need->getAttribute('title') : implode(' · ', $parts);
    }

    private static function stageText(PurchaseOrder $record, PurchaseOrderStatus $stage): string
    {
        $time = self::stageTime($record, $stage);

        if ($time !== null) {
            return $time;
        }

        if (in_array($stage, [PurchaseOrderStatus::AwaitingApproval, PurchaseOrderStatus::Approved], true)
            && ! PurchaseOrderActions::approvalState($record)->required) {
            return __('purchase_order.timeline.skipped');
        }

        if ($record->statusEnum() === $stage) {
            return __('purchase_order.timeline.current');
        }

        return self::reached($record, $stage) ? '✓' : __('purchase_order.timeline.pending');
    }

    private static function stageTone(PurchaseOrder $record, PurchaseOrderStatus $stage): string
    {
        if ($record->statusEnum() === $stage) {
            return 'primary';
        }

        return self::reached($record, $stage) || self::stageTime($record, $stage) !== null ? 'success' : 'gray';
    }

    /** Asama gecildi mi (iptal sipariste yalniz zamani olan asamalar). */
    private static function reached(PurchaseOrder $record, PurchaseOrderStatus $stage): bool
    {
        $stages = PurchaseOrderStatus::stages();
        $current = array_search($record->statusEnum(), $stages, true);
        $index = array_search($stage, $stages, true);

        return $current !== false && $index !== false && $index < $current;
    }

    private static function stageTime(PurchaseOrder $record, PurchaseOrderStatus $stage): ?string
    {
        $request = $record->getAttribute('approval_request_id') !== null ? $record->approvalRequest : null;

        return match ($stage) {
            PurchaseOrderStatus::Draft => self::moment($record->getAttribute('created_at')),
            PurchaseOrderStatus::AwaitingApproval => $request instanceof ApprovalRequest ? self::moment($request->getAttribute('requested_at') ?? $request->getAttribute('created_at')) : null,
            PurchaseOrderStatus::Approved => $request instanceof ApprovalRequest && $record->statusEnum()->isApprovedOrLater() ? self::moment($request->getAttribute('decided_at')) : null,
            PurchaseOrderStatus::Ordered => self::moment($record->getAttribute('ordered_at')),
            PurchaseOrderStatus::InTransit => self::moment($record->getAttribute('shipped_at')),
            PurchaseOrderStatus::Delivered => self::moment($record->getAttribute('delivered_at')),
            PurchaseOrderStatus::Completed => self::moment($record->getAttribute('completed_at')),
            default => null,
        };
    }

    private static function moment(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->copy()->timezone(DisplayTime::zone())->format('d.m.Y H:i') : null;
    }
}
