<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds;

use App\Filament\Support\RecordLinks;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseQuotation;
use App\Query\Procurement\PurchaseRequestQueries;
use Filament\Tables\Columns\TextColumn;

/**
 * Satin alinacak kalemin bagli kayit sutunlari (D-192, B53; kullanici:
 * "Kalem olarak hangi teklife bagli oldugu gorulmeli"). Satin alinacaklar
 * listesi ve talep sayfasinin kalem tablosu ayni sutunlari kullanir:
 *
 * - Teklif: kalemin girdigi satin alma teklifi (en yenisi) kodu, durum
 *   renginde rozet ve teklif sayfasina baglanti; altta tedarikci, durum ve
 *   baska teklif varsa "+n".
 * - Siparis: SIP kodu, durum renginde rozet, baglanti; altta durum.
 *
 * Sorgu PurchaseRequestQueries::withLineRelations ile yuklenir.
 */
final class PurchaseNeedColumns
{
    public static function quotation(): TextColumn
    {
        $first = static fn (PurchaseNeed $record): ?PurchaseQuotation => app(PurchaseRequestQueries::class)->quotationsOf($record)[0] ?? null;

        return TextColumn::make('quotation')
            ->label(__('purchasing.fields.quotation'))
            ->state(fn (PurchaseNeed $record): ?string => $first($record)?->getAttribute('quotation_no'))
            ->badge()
            ->color(fn (PurchaseNeed $record): ?string => $first($record)?->statusEnum()->getColor())
            ->icon(fn (PurchaseNeed $record): mixed => ($quotation = $first($record)) instanceof PurchaseQuotation ? RecordLinks::iconFor($quotation) : null)
            ->description(function (PurchaseNeed $record): ?string {
                $quotations = app(PurchaseRequestQueries::class)->quotationsOf($record);
                $quotation = $quotations[0] ?? null;

                if (! $quotation instanceof PurchaseQuotation) {
                    return null;
                }

                $parts = array_filter([
                    $quotation->supplierLabel(),
                    (string) $quotation->statusEnum()->getLabel(),
                    count($quotations) > 1 ? __('purchasing.values.more', ['count' => count($quotations) - 1]) : null,
                ], static fn (?string $part): bool => filled($part));

                return implode(' · ', $parts);
            })
            ->tooltip(fn (PurchaseNeed $record): ?string => count($quotations = app(PurchaseRequestQueries::class)->quotationsOf($record)) > 1
                ? implode(', ', array_map(static fn (PurchaseQuotation $quotation): string => $quotation->getAttribute('quotation_no').' ('.$quotation->supplierLabel().')', $quotations))
                : null)
            ->url(fn (PurchaseNeed $record): ?string => ($quotation = $first($record)) instanceof PurchaseQuotation ? RecordLinks::detailUrl($quotation, checkRecord: false) : null)
            ->placeholder('-');
    }

    public static function order(): TextColumn
    {
        $order = static fn (PurchaseNeed $record): ?PurchaseOrder => app(PurchaseRequestQueries::class)->orderOf($record);

        return TextColumn::make('purchase_order')
            ->label(__('purchasing.fields.order'))
            ->state(fn (PurchaseNeed $record): ?string => $order($record)?->getAttribute('order_no'))
            ->badge()
            ->color(fn (PurchaseNeed $record): ?string => $order($record)?->statusEnum()->getColor())
            ->icon(fn (PurchaseNeed $record): mixed => ($found = $order($record)) instanceof PurchaseOrder ? RecordLinks::iconFor($found) : null)
            ->description(fn (PurchaseNeed $record): ?string => ($found = $order($record)) instanceof PurchaseOrder ? (string) $found->statusEnum()->getLabel() : null)
            ->url(fn (PurchaseNeed $record): ?string => ($found = $order($record)) instanceof PurchaseOrder ? RecordLinks::detailUrl($found, checkRecord: false) : null)
            ->placeholder('-');
    }
}
