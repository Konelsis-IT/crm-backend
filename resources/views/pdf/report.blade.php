{{--
    Rapora ozel PDF (D-167, 6 Ekim 2026 kullanici onayi: rapor PDF sablonu).
    Ust bilgi kurum / rapor turu / baslik / rapor no / durum; bilgi tablosu
    hazirlayan, donem, ilgili kayit, tarihler; govde rapor detayindaki bicimli
    metnin aynisi (App\Reports\Formatting\ReportFormatter::pdfBody, guvenli
    HTML); alt bilgi her sayfada sayfa numarasiyla. dompdf + DejaVu Sans.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $reportNo }} · {{ $title }}</title>
    <style>
        @page { margin: 30px 36px 58px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 70px; height: 26px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8.5px; color: #6b7280; }
        .head { border-bottom: 2px solid #dc2626; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { vertical-align: bottom; }
        .logo { height: 30px; }
        .company { font-size: 9px; color: #6b7280; letter-spacing: .4px; }
        .kind { font-size: 10px; color: #dc2626; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }
        h1 { font-size: 17px; margin: 2px 0 0; color: #111827; }
        .badge-cell { width: 170px; text-align: right; }
        .no { font-size: 12px; font-weight: bold; color: #111827; }
        .status { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 1px solid #fca5a5; border-radius: 9px; color: #991b1b; font-size: 9px; }
        .info { margin-bottom: 14px; }
        .info th, .info td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; vertical-align: top; }
        .info th { width: 17%; background: #f9fafb; font-weight: bold; color: #374151; }
        .info td { width: 33%; }
        .body h1 { display: none; }
        .body h2 { font-size: 13px; color: #991b1b; border-bottom: 1px solid #fecaca; padding-bottom: 3px; margin: 16px 0 8px; page-break-after: avoid; }
        .body h3 { font-size: 11.5px; color: #374151; margin: 12px 0 6px; page-break-after: avoid; }
        .body li { page-break-inside: avoid; }
        .body p { margin: 0 0 7px; }
        .body ul, .body ol { margin: 0 0 8px; padding-left: 16px; }
        .body li { margin: 0 0 5px; }
        .body li p { margin: 0 0 4px; }
        .body blockquote { margin: 3px 0 5px; padding: 3px 10px; border-left: 3px solid #e5e7eb; color: #4b5563; }
        .body blockquote p { margin: 0; }
        .body em { color: #6b7280; font-style: normal; }
        .body strong { color: #111827; }
        .body hr { border: 0; border-top: 1px solid #e5e7eb; margin: 12px 0; }
    </style>
</head>
<body>
    {{-- Sag alttaki "Sayfa X / Y" cizimden sonra eklenir (App\Filament\Exports\ReportPdf::render). --}}
    <div class="footer">
        {{ $company }} · {{ $reportNo }} · {{ __('report.text.author') }}: {{ $author }}@if ($exportedBy !== '') · {{ __('report.pdf.downloaded_by') }}: {{ $exportedBy }}, {{ $exportedAt }}@endif
    </div>

    <table class="head">
        <tr>
            <td>
                @if (is_file(public_path('images/konelsis-logo.png')))
                    <img class="logo" src="{{ public_path('images/konelsis-logo.png') }}" alt="{{ $company }}">
                @else
                    <div class="company">{{ $company }}</div>
                @endif
                <div class="kind">{{ $kind }}</div>
                <h1>{{ $title }}</h1>
            </td>
            <td class="badge-cell">
                <div class="no">{{ $reportNo }}</div>
                <div class="status">{{ $status }}</div>
            </td>
        </tr>
    </table>

    @if ($facts !== [])
        <table class="info">
            @foreach (array_chunk($facts, 2) as $pair)
                <tr>
                    @foreach ($pair as $fact)
                        <th>{{ $fact['label'] }}</th>
                        <td>{{ $fact['value'] }}</td>
                    @endforeach
                    @if (count($pair) === 1)
                        <th></th>
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    {{-- $body bir HtmlString'dir (CommonMark, ham HTML kacirilmis); oldugu gibi basilir. --}}
    <div class="body">{{ $body }}</div>
</body>
</html>
