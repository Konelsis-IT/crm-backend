<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\ProjectReference;
use App\Support\Acquisition\ScopeTypes;
use App\Support\DisplayTime;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Referans listesinin Excel'i (D-177, 8 Ekim 2026 kullanici talimati: "Excel
 * formatinda, buraya koydugum formatta indirilebilir yapmalisin").
 *
 * Bicim kullanicinin bes dosyasindan (GES / HES / RES / OTOMASYON / TM+ENH
 * REFERANSLAR.xlsx) alindi:
 * - Her grup ayri sayfa ("GES REFERANSLAR"); ilk satir A1:B1 birlesik baslik
 *   ("GES REFERANSLARIMIZ"), kirmizi dolgu (FF0000), beyaz kalin Calibri 9.
 * - Sonraki satirlar: A sira no (kirmizi dolgu, beyaz Calibri 9, ortali),
 *   B referans metni (Calibri 9, sola dayali, satir kaydirmali); butun
 *   hucrelerde ince siyah cerceve. A sutunu 9,14, B sutunu 81,29 genislik.
 * - Gruplar dosyalardaki gibidir: GES, HES, RES, BESS, PROSES (Otomasyon /
 *   Process) ve DIGER (TM + ENH/EIH tek sayfa). Bir referans birden fazla
 *   gruptaysa her grubun sayfasinda yer alir. Sira no sayfada 1'den baslar.
 *
 * Hucreler metin / sayi olarak yazilir (formul enjeksiyonu yok). OpenSpout
 * (Filament ile kurulu); kuyruk ve veritabani kaydi yok.
 */
final class ReferenceWorkbook
{
    /**
     * Grup => [sayfa adi, baslik, tipler]. Sira dosyalarin sirasidir.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public const GROUPS = [
        'ges' => ['GES REFERANSLAR', 'GES REFERANSLARIMIZ', ['ges']],
        'hes' => ['HES REFERANSLAR', 'HES REFERANSLARIMIZ', ['hes']],
        'res' => ['RES REFERANSLAR', 'RES REFERANSLARIMIZ', ['res']],
        'bes' => ['BESS REFERANSLAR', 'BESS REFERANSLARIMIZ', ['bes']],
        'automation' => ['OTOMASYON REFERANSLAR', 'PROSES REFERANSLARIMIZ', ['automation']],
        'other' => ['TM+ENH REFERANSLAR', 'DİĞER REFERANSLARIMIZ', ['tm', 'enh_eih']],
    ];

    /** Dosyalardaki dolgu (ARGB, styles.xml'deki gibi). */
    private const RED = 'FFFF0000';

    private const FONT = 'Calibri';

    private const FONT_SIZE = 9;

    /**
     * $types: istenen tipler (bos = hepsi). Grubun tiplerinden biri istenmisse
     * grup sayfasi yazilir; sayfaya o grubun tiplerine bagli referanslar girer.
     *
     * @param  iterable<ProjectReference>  $references  arsivde olmayan, sirali referanslar (tipleri yuklu)
     * @param  iterable<mixed>  $types
     */
    public function download(iterable $references, iterable $types = []): StreamedResponse
    {
        $wanted = ScopeTypes::values($types);
        $path = $this->write($references, $wanted);

        return response()->streamDownload(
            static function () use ($path): void {
                readfile($path);
                @unlink($path);
            },
            self::fileName($wanted),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Gecici dosyaya yazar ve yolunu verir.
     *
     * @param  iterable<ProjectReference>  $references
     * @param  list<string>  $wanted
     */
    public function write(iterable $references, array $wanted): string
    {
        $groups = $this->groups($references, $wanted);
        $path = (string) tempnam(sys_get_temp_dir(), 'konelsis-references-');

        $options = new Options;
        $writer = new Writer($options);
        $writer->openToFile($path);

        $border = new Border(
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN),
        );
        $title = (new Style)->setFontName(self::FONT)->setFontSize(self::FONT_SIZE)->setFontBold()->setFontColor(Color::WHITE)
            ->setBackgroundColor(self::RED)->setBorder($border)->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP)->setShouldWrapText();
        $number = (new Style)->setFontName(self::FONT)->setFontSize(self::FONT_SIZE)->setFontColor(Color::WHITE)
            ->setBackgroundColor(self::RED)->setBorder($border)->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setShouldWrapText();
        $text = (new Style)->setFontName(self::FONT)->setFontSize(self::FONT_SIZE)->setFontColor(Color::BLACK)
            ->setBorder($border)->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setShouldWrapText();

        $first = true;

        foreach ($groups as $key => $rows) {
            [$sheetName, $heading] = self::GROUPS[$key];
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $first = false;

            $sheet->setName($sheetName);
            $sheet->setColumnWidth(9.140625, 1);
            $sheet->setColumnWidth(81.28515625, 2);
            $options->mergeCells(0, 1, 1, 1, $sheet->getIndex());

            $writer->addRow(new Row([new StringCell($heading, $title), new StringCell('', $title)]));

            foreach ($rows as $index => $reference) {
                $writer->addRow(new Row([
                    new NumericCell($index + 1, $number),
                    new StringCell((string) $reference->title, $text),
                ]));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * Grup => referanslar. Hic referans yoksa istenen ilk grubun bos sayfasi.
     *
     * @param  iterable<ProjectReference>  $references
     * @param  list<string>  $wanted
     * @return array<string, list<ProjectReference>>
     */
    private function groups(iterable $references, array $wanted): array
    {
        $groups = [];

        foreach (self::GROUPS as $key => [, , $types]) {
            if ($wanted === [] || array_intersect($types, $wanted) !== []) {
                $groups[$key] = [];
            }
        }

        foreach ($references as $reference) {
            $values = array_map(static fn (ProjectScopeType $type): string => $type->value, $reference->types());

            foreach (array_keys($groups) as $key) {
                if (array_intersect(self::GROUPS[$key][2], $values) !== []) {
                    $groups[$key][] = $reference;
                }
            }
        }

        $filled = array_filter($groups, static fn (array $rows): bool => $rows !== []);

        if ($filled !== []) {
            return $filled;
        }

        $firstKey = array_key_first($groups) ?? 'ges';

        return [$firstKey => []];
    }

    /**
     * Dosya adi: Referanslar_GES_TM_08.10.2026.xlsx (tip yoksa Referanslar_...).
     * D-184: teklif Dokumanlar tablosundaki "Referans listesi" satiri ve ZIP de bu adi kullanir.
     *
     * @param  list<string>  $wanted
     */
    public static function fileName(array $wanted): string
    {
        $labels = array_map(
            static fn (string $value): string => str_replace(['/', ' '], ['-', ''], (string) ProjectScopeType::from($value)->getLabel()),
            $wanted,
        );

        return implode('_', array_filter(['Referanslar', ...$labels, now()->timezone(DisplayTime::zone())->format('d.m.Y')])).'.xlsx';
    }
}
