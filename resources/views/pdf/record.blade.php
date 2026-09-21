{{--
    Ayrinti sayfasi PDF'i (D-110, B35). dompdf HTML'den PDF uretir; Filament'in
    PDF bileseni olmadigi icin bu sade sablon gereklidir (21 Eylul 2026 kullanici
    onayi: barryvdh/laravel-dompdf). DejaVu Sans Turkce karakterleri basar.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #1f2937; }
        .head { border-bottom: 2px solid #dc2626; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: bottom; }
        .logo { height: 30px; }
        .kind { font-size: 10px; color: #dc2626; text-transform: uppercase; letter-spacing: .5px; }
        h1 { font-size: 16px; margin: 2px 0 0; }
        .meta { width: 150px; text-align: right; font-size: 9px; color: #6b7280; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; }
        .rows th, .rows td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        .rows th { width: 32%; background: #f9fafb; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if (is_file(public_path('images/konelsis-logo.png')))
                    <img class="logo" src="{{ public_path('images/konelsis-logo.png') }}" alt="Konelsis">
                @endif
                <div class="kind">{{ $heading }}</div>
                <h1>{{ $title }}</h1>
            </td>
            <td class="meta">
                {{ __('export.pdf.exported_by') }}: {{ $exportedBy }}<br>
                {{ __('export.pdf.exported_at') }}: {{ $exportedAt }}
            </td>
        </tr>
    </table>

    <table class="rows">
        @foreach ($rows as $label => $value)
            <tr>
                <th>{{ $label }}</th>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
