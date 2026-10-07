<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Report\Report;
use App\Reports\Formatting\ReportFormatter;
use App\Support\DisplayTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapora ozel PDF (D-167, 6 Ekim 2026 kullanici karari: "Rapor pdf ciktisi
 * rapora ozel bir pdf yapisinda cikti vermelidir. Ilgili personel, ilgili
 * islem vs. net belli olmali ... kopyalanan kisimdaki metin ayni formatta
 * pdf'e aktarilmalidir").
 *
 * Ust bilgi: kurum, rapor turu, baslik, rapor no ve durum; bilgi tablosu:
 * hazirlayan, donem, ilgili kayit, olusturma / gonderim / inceleme; govde:
 * rapor detayindaki bicimli metnin aynisi (ReportFormatter, simgesiz); alt
 * bilgi: kurum, rapor no, hazirlayan, indiren ve sayfa numarasi.
 * dompdf + DejaVu Sans (Turkce karakterler).
 */
final class ReportPdf
{
    public function __construct(private readonly ReportFormatter $formatter) {}

    public function download(Report $report): StreamedResponse
    {
        Gate::authorize('view', $report);

        $bytes = $this->render($report);

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            self::fileName($report, 'pdf'),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * PDF icerigi. Sayfa numarasi ("Sayfa 2 / 5") cizimden sonra her sayfanin
     * sag altina yazilir (dompdf CSS'te toplam sayfa sayisini veremez; PHP
     * betigi acilmaz).
     */
    public function render(Report $report): string
    {
        $pdf = Pdf::loadView('pdf.report', $this->viewData($report))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $text = __('report.pdf.page').' {PAGE_NUM} / {PAGE_COUNT}';
        $size = 6.5;
        $width = $dompdf->getFontMetrics()->getTextWidth(str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], '88', $text), $font, $size);

        // A4 (595 x 842 pt), sag kenar bosluk 36px = 27pt; alt bilgi satiri hizasi.
        $canvas->page_text($canvas->get_width() - 27 - $width, $canvas->get_height() - 31, $text, $font, $size, [0.42, 0.45, 0.5]);

        return (string) $pdf->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Report $report): array
    {
        $facts = $this->formatter->facts($report);

        return [
            'company' => (string) config('konelsis.organization.name_tr', 'Konelsis'),
            'kind' => $report->templateName(),
            'title' => (string) ($report->title ?: $report->templateName()),
            'reportNo' => (string) $report->report_no,
            'status' => (string) $report->status->getLabel(),
            'author' => (string) ($report->author?->full_name ?? ''),
            // Ust bilgide yazilanlar (no, tur, durum) tabloda tekrar edilmez.
            'facts' => array_values(array_filter($facts, static fn (array $fact): bool => ! in_array($fact['key'], ['report_no', 'template', 'status'], true))),
            'body' => new HtmlString($this->formatter->pdfBody($report)),
            'exportedBy' => (string) (auth()->user()?->full_name ?? ''),
            'exportedAt' => now()->timezone(DisplayTime::zone())->format('d.m.Y H:i'),
        ];
    }

    /** Dosya adi: rapor no + baslik (Turkce harfler ASCII'ye cevrilir). */
    public static function fileName(Report $report, string $extension): string
    {
        return Str::of((string) $report->report_no.' '.(string) ($report->title ?: $report->templateName()))
            ->slug()
            ->limit(90, '')
            ->append('.'.$extension)
            ->value();
    }
}
