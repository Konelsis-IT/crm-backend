{{--
    Satin alma teklifi ve siparis PDF'inin ortak sablonu (D-192; kullanici:
    "8. ekran goruntusu bizdeki tekliflerin standart pdf goruntuleme sekli
    olur"; Blade kullanici onayi D-192: "PDF sablonu bu sekilde yapilir").
    Veriyi App\Filament\Exports\PurchaseDocumentPdf hazirlar; burada hesap
    yoktur. dompdf: kutu golgesi yok, duzen tablolarla; DejaVu Sans Turkce
    harfleri ve para simgelerini basar.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $code }} · {{ $title }}</title>
    <style>
        @page { margin: 26px 28px 54px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #1f2937; background: #f4f6f9; }
        table { width: 100%; border-collapse: collapse; }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; height: 22px; border-top: 1px solid #d9dee5; padding-top: 6px; font-size: 8px; color: #6b7280; text-align: center; }
        .card { background: #ffffff; border: 1px solid #e3e8ef; border-radius: 9px; padding: 14px 16px; margin-bottom: 10px; }
        .band { background: #ffffff; border: 1px solid #e3e8ef; border-radius: 9px; padding: 11px 16px; margin-bottom: 12px; }
        .band h1 { margin: 0; font-size: 17px; color: #111827; }
        .band td { vertical-align: middle; }
        .status { text-align: right; font-size: 9px; font-weight: bold; color: #0a7cc1; }
        .status span { border: 1px solid #9fd3f2; border-radius: 9px; padding: 3px 9px; background: #eef8fe; }
        .company-name { font-size: 15px; font-weight: bold; color: #0a7cc1; margin: 4px 0 2px; }
        .company-sub { font-size: 8px; color: #6b7280; }
        .logo { height: 26px; }
        .code { text-align: right; font-size: 16px; font-weight: bold; color: #f2711c; }
        .dates { text-align: right; font-size: 9px; color: #4b5563; line-height: 1.6; }
        h2 { font-size: 12px; color: #0a7cc1; margin: 0 0 8px; padding-bottom: 7px; border-bottom: 1px solid #e5e7eb; }
        .info td { vertical-align: top; padding: 2px 8px 0 0; }
        .info .label { font-size: 7.5px; color: #0a7cc1; letter-spacing: .3px; }
        .info .value { font-size: 9.5px; color: #111827; padding-top: 2px; }
        .items th { background: #0a7cc1; color: #ffffff; font-size: 8px; letter-spacing: .3px; text-align: left; padding: 8px 7px; border-right: 1px solid #3c9bd3; }
        .items th.first { border-top-left-radius: 7px; }
        .items th.last { border-top-right-radius: 7px; border-right: 0; }
        .items td { padding: 7px 7px; border-bottom: 1px solid #eef1f5; vertical-align: top; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .detail { display: block; font-size: 7.5px; color: #6b7280; margin-top: 2px; }
        .items .flag { display: inline-block; font-size: 7px; border: 1px solid #f5b58a; color: #b4530f; border-radius: 6px; padding: 0 4px; margin-left: 4px; }
        .items tr.excluded td { color: #9ca3af; }
        .items tr.excluded .amount { text-decoration: line-through; }
        .bottom td.col { vertical-align: top; }
        .facts td { padding: 4px 0; border-bottom: 1px solid #f0f2f5; vertical-align: top; }
        .facts .label { width: 38%; color: #0a7cc1; font-size: 8.5px; }
        .facts.pairs .label { width: 17%; }
        .facts.pairs .value { width: 33%; padding-right: 12px; white-space: pre-line; }
        .note { margin-top: 8px; font-size: 9px; color: #374151; white-space: pre-line; }
        .totals td { padding: 7px 0; border-bottom: 1px solid #edf0f4; font-size: 10px; }
        .totals .label { color: #0a7cc1; font-weight: bold; }
        .totals .value { text-align: right; }
        .totals .red .value { color: #dc2626; font-weight: bold; }
        .totals .muted td { color: #6b7280; font-size: 8.5px; border-bottom: 0; }
        .totals .grand td { border-bottom: 2px solid #0a7cc1; padding-top: 11px; }
        .totals .grand .label { font-size: 12.5px; }
        .totals .grand .value { font-size: 14px; font-weight: bold; color: #f2711c; }
        .empty { color: #9ca3af; text-align: center; padding: 14px; }
    </style>
</head>
<body>
    <div class="footer">
        {{ __('procurement.pdf.footer', ['company' => $company]) }} | {{ $printedAt }}@if ($preparedBy !== '') | {{ __('procurement.pdf.prepared_by') }}: {{ $preparedBy }}@endif
    </div>

    {{-- Ust bant: belge adi ve durum --}}
    <div class="band">
        <table>
            <tr>
                <td><h1>{{ $title }}</h1></td>
                <td class="status"><span>{{ $status }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Kurum karti: logo + ad; sagda belge kodu ve tarihler --}}
    <div class="card">
        <table>
            <tr>
                <td style="vertical-align: top;">
                    @if ($logo !== null)
                        <img class="logo" src="{{ $logo }}" alt="{{ $company }}">
                    @endif
                    <div class="company-name">{{ $company }}</div>
                    @if ($supplier['reference'] !== null && $kind === 'quotation')
                        <div class="company-sub">{{ __('procurement.pdf.supplier_reference') }}: {{ $supplier['reference'] }}</div>
                    @endif
                </td>
                <td style="vertical-align: top; width: 48%;">
                    <div class="code">{{ $code }}</div>
                    <div class="dates">
                        @foreach ($dates as $date)
                            {{ $date['label'] }}: {{ $date['value'] }}<br>
                        @endforeach
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Tedarikci bilgileri --}}
    <div class="card">
        <h2>{{ __('procurement.pdf.supplier') }}</h2>
        <table class="info">
            <tr>
                <td style="width: 27%;"><div class="label">{{ $upper(__('procurement.pdf.name')) }}</div><div class="value">{{ $supplier['name'] }}</div></td>
                <td style="width: 20%;"><div class="label">{{ $upper(__('procurement.pdf.phone')) }}</div><div class="value">{{ $supplier['phone'] ?? __('procurement.pdf.none') }}</div></td>
                <td style="width: 23%;"><div class="label">{{ $upper(__('procurement.pdf.email')) }}</div><div class="value">{{ $supplier['email'] ?? __('procurement.pdf.none') }}</div></td>
                <td style="width: 30%;"><div class="label">{{ $upper(__('procurement.pdf.address')) }}</div><div class="value">{{ $supplier['address'] ?? __('procurement.pdf.none') }}</div></td>
            </tr>
        </table>
    </div>

    {{-- Siparis: teslimat bilgileri --}}
    @if ($kind === 'order' && ($delivery ?? []) !== [])
        <div class="card">
            <h2>{{ __('procurement.pdf.delivery') }}</h2>
            <table class="facts pairs">
                @foreach (array_chunk($delivery, 2) as $pair)
                    <tr>
                        @foreach ($pair as $fact)
                            <td class="label">{{ $fact['label'] }}</td>
                            <td class="value">{{ $fact['value'] }}</td>
                        @endforeach
                        @if (count($pair) === 1)
                            <td class="label"></td><td class="value"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Kalemler --}}
    <div class="card">
        <h2>{{ __('procurement.pdf.items') }}</h2>
        <table class="items">
            <thead>
                <tr>
                    <th class="first center" style="width: 6%;">{{ $upper(__('procurement.pdf.no')) }}</th>
                    <th style="width: 38%;">{{ $upper(__('procurement.pdf.description')) }}</th>
                    <th class="num" style="width: 9%;">{{ $upper(__('procurement.pdf.quantity')) }}</th>
                    <th style="width: 8%;">{{ $upper(__('procurement.pdf.unit')) }}</th>
                    <th class="num" style="width: 14%;">{{ $upper(__('procurement.pdf.unit_price')) }}</th>
                    <th class="num" style="width: 9%;">{{ $upper(__('procurement.pdf.discount')) }}</th>
                    <th class="last num" style="width: 16%;">{{ $upper(__('procurement.pdf.amount')) }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr class="{{ $line['excluded'] ? 'excluded' : '' }}">
                        <td class="center">{{ $line['no'] }}</td>
                        <td>
                            {{ $line['description'] }}
                            @if ($line['flag'] !== null)
                                <span class="flag">{{ $line['flag'] }}</span>
                            @endif
                            @if ($line['detail'] !== null)
                                <span class="detail">{{ $line['detail'] }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $line['quantity'] }}</td>
                        <td>{{ $line['unit'] }}</td>
                        <td class="num">{{ $line['unit_price'] }}</td>
                        <td class="num">{{ $line['discount'] }}</td>
                        <td class="num amount">{{ $line['amount'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">{{ __('procurement.pdf.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Altta: solda sartlar ve not, sagda toplamlar --}}
    <table class="bottom">
        <tr>
            <td class="col" style="width: 52%; padding-right: 10px;">
                @if ($facts !== [] || $note !== null)
                    <div class="card">
                        <h2>{{ $factsTitle }}</h2>
                        @if ($facts !== [])
                            <table class="facts">
                                @foreach ($facts as $fact)
                                    <tr>
                                        <td class="label">{{ $fact['label'] }}</td>
                                        <td>{{ $fact['value'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                        @if ($note !== null)
                            <div class="note"><strong>{{ __('procurement.pdf.note') }}:</strong> {{ $note }}</div>
                        @endif
                    </div>
                @endif
            </td>
            <td class="col" style="width: 48%;">
                <div class="card">
                    <table class="totals">
                        @foreach ($totals as $row)
                            <tr class="{{ $row['tone'] ?? '' }}">
                                <td class="label">{{ $row['label'] }}</td>
                                <td class="value">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
