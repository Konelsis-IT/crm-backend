{{--
    Satin alma teklifleri "Karsilastir" penceresi (D-192; Blade kullanici
    onayiyla: Filament tablosu sutunlari tedarikciye gore donduremez). Satirlar
    kalemler (Satin alinacak kalem / katalog kalemi / aciklama), sutunlar
    teklifler; hucrede satir tutari ve birim fiyat, satirin en uygunu yesil
    (dovizli teklif TL karsiligiyla), eksik hucre "–", altta toplamlar. Veri
    App\Query\Procurement\PurchaseQuotationQueries::comparisonMatrix.
--}}
@php
    use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
    use App\Support\Money;
@endphp

<div class="kcp-matrix-wrap">
    @if ($tooFew)
        <p class="kcp-sub">{{ __('purchase_quotation.messages.compare_min') }}</p>
    @elseif ($matrix['rows'] === [])
        <p class="kcp-sub">{{ __('purchase_quotation.compare.empty') }}</p>
    @else
        <table class="kcp-matrix">
            <thead>
                <tr>
                    <th>{{ __('purchase_quotation.compare.item') }}</th>
                    @foreach ($matrix['columns'] as $column)
                        <th class="kcp-num">
                            <a href="{{ PurchaseQuotationResource::getUrl('view', ['record' => $column['id']]) }}">{{ $column['supplier'] !== '' ? $column['supplier'] : __('purchase_quotation.values.no_supplier') }}</a>
                            <span class="kcp-sub">{{ $column['code'] }} · {{ $column['status']->getLabel() }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix['rows'] as $row)
                    <tr>
                        <td>
                            {{ $row['label'] }}
                            @if (filled($row['sub']))
                                <span class="kcp-sub">{{ $row['sub'] }}</span>
                            @endif
                        </td>
                        @foreach ($matrix['columns'] as $column)
                            @php($cell = $row['cells'][$column['id']] ?? null)
                            @if ($cell === null)
                                <td class="kcp-missing">–</td>
                            @else
                                <td class="kcp-num {{ $cell['best'] ? 'kcp-best' : '' }}">
                                    {{ Money::format($cell['total'], $cell['currency']) }}
                                    <span class="kcp-sub">
                                        {{ Money::format($cell['unit_price'], $cell['currency'], '-', 4) }} × {{ Money::number($cell['quantity'], 2) }}
                                        @if ($cell['option'])
                                            · {{ __('purchase_quotation.compare.option') }}
                                        @endif
                                        @if (strtoupper($cell['currency']) !== 'TRY' && $cell['total_try'] !== null)
                                            · {{ Money::format($cell['total_try'], 'TRY') }}
                                        @endif
                                    </span>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>{{ __('purchase_quotation.compare.total') }}</td>
                    @foreach ($matrix['columns'] as $column)
                        <td class="kcp-num {{ $matrix['best_total'] === $column['id'] ? 'kcp-best' : '' }}">
                            {{ Money::format($column['total'], $column['currency']) }}
                            @if (strtoupper($column['currency']) !== 'TRY')
                                <span class="kcp-sub">{{ __('purchase_quotation.compare.in_try') }}: {{ Money::format($column['total_try'], 'TRY') }}</span>
                            @endif
                            @if ($column['lead_time_days'] !== null || $column['delivery_on'] !== null)
                                <span class="kcp-sub">{{ __('purchase_quotation.compare.delivery') }}: {{ implode(' · ', array_filter([$column['lead_time_days'] !== null ? __('purchase_quotation.values.days', ['n' => $column['lead_time_days']]) : null, $column['delivery_on']])) }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    @endif
</div>
