<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheet;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Yalin XLSX okuyucu (D-189): istenen sayfalarin satirlarini hucre harfi =>
 * metin ve satirin dolgu rengiyle verir. Satir rengi maliyet listesinde fiyat
 * durumudur (yesil FF92D050 kesin, sari FFFFFF00 netlesmedi); OpenSpout dolgu
 * rengini okumadigi icin XML dogrudan okunur. Yeni paket yok (ZipArchive +
 * SimpleXML). Yalniz okunur; dosyaya yazmaz.
 */
final class XlsxSheetReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @param  list<string>|null  $only  Okunacak sayfa adlari (null: hepsi).
     * @return array<string, list<array{r: int, fill: string, cells: array<string, string>}>>
     */
    public function read(string $path, ?array $only = null): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('xlsx');
        }

        try {
            $shared = $this->sharedStrings($zip);
            $xfFill = $this->cellFills($zip);
            $result = [];

            foreach ($this->sheetFiles($zip) as $name => $file) {
                if ($only !== null && ! in_array($name, $only, true)) {
                    continue;
                }

                $xml = $zip->getFromName($file);
                $result[$name] = $xml === false ? [] : $this->rows($xml, $shared, $xfFill);
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    /**
     * Calisma kitabindaki sayfa adlari (siradaki gibi).
     *
     * @return list<string>
     */
    public function sheetNames(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('xlsx');
        }

        try {
            return array_keys($this->sheetFiles($zip));
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];
        $doc = $this->load($xml);

        foreach ($doc->si as $si) {
            $si->registerXPathNamespace('m', self::NS);
            $parts = [];

            foreach ($si->xpath('.//m:t') ?: [] as $text) {
                $parts[] = (string) $text;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * Hucre bicimi (cellXfs sirasi) => dolgu rengi ("FF92D050", "theme5" ya da "").
     *
     * @return list<string>
     */
    private function cellFills(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');

        if ($xml === false) {
            return [];
        }

        $styles = $this->load($xml);
        $fills = [];

        foreach ($styles->fills->fill ?? [] as $fill) {
            $color = $fill->patternFill !== null && isset($fill->patternFill->fgColor) ? $fill->patternFill->fgColor->attributes() : null;
            $fills[] = $color !== null && isset($color['rgb'])
                ? strtoupper((string) $color['rgb'])
                : ($color !== null && isset($color['theme']) ? 'theme'.$color['theme'] : '');
        }

        $result = [];

        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $result[] = $fills[(int) $xf['fillId']] ?? '';
        }

        return $result;
    }

    /**
     * Sayfa adi => paket icindeki XML yolu.
     *
     * @return array<string, string>
     */
    private function sheetFiles(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relations = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relations === false) {
            throw new RuntimeException('xlsx');
        }

        $targets = [];

        foreach ($this->load($relations)->Relationship as $relation) {
            $targets[(string) $relation['Id']] = (string) $relation['Target'];
        }

        $files = [];

        foreach ($this->load($workbook)->sheets->sheet as $sheet) {
            $id = (string) $sheet->attributes('r', true)['id'];
            $target = $targets[$id] ?? null;

            if ($target !== null) {
                $files[(string) $sheet['name']] = 'xl/'.ltrim(str_replace('/xl/', '', $target), '/');
            }
        }

        return $files;
    }

    /**
     * @param  list<string>  $shared
     * @param  list<string>  $xfFill
     * @return list<array{r: int, fill: string, cells: array<string, string>}>
     */
    private function rows(string $xml, array $shared, array $xfFill): array
    {
        $sheet = $this->load($xml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            $fill = '';

            foreach ($row->c as $cell) {
                $column = (string) preg_replace('/\d+/', '', (string) $cell['r']);
                $cellFill = $xfFill[(int) $cell['s']] ?? '';

                if ($cellFill !== '' && $cellFill !== 'theme0') {
                    $fill = $cellFill;
                }

                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                }

                $value = trim((string) preg_replace("/[ \t\x{00A0}]+/u", ' ', str_replace(["\r\n", "\r"], "\n", $value)));

                if ($value !== '') {
                    $cells[$column] = $value;
                }
            }

            if ($cells !== []) {
                $rows[] = ['r' => (int) $row['r'], 'fill' => $fill, 'cells' => $cells];
            }
        }

        return $rows;
    }

    private function load(string $xml): SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        if ($doc === false) {
            throw new RuntimeException('xlsx');
        }

        return $doc;
    }
}
