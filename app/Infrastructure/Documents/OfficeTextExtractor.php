<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Infrastructure\Database\Spreadsheet\XlsxSheetReader;
use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Word (.docx) ve Excel (.xlsx / .xlsm) dosyalarindan duz metin cikarir
 * (D-192: Konelsis AI'ye yalniz metin gider; ai-service kayit 29). Yeni paket
 * yok: DOCX icin ZipArchive + DOM (word/document.xml), XLSX icin projedeki
 * XlsxSheetReader. Eski ikili .doc / .xls okunmaz. Dosyaya yazmaz.
 *
 * Cikti: paragraflar satir satir; Word tablolari ve Excel satirlari hucreleri
 * sekmeyle ayrilmis satirlar; her Excel sayfasi "# Sayfa adi" ile baslar.
 */
final class OfficeTextExtractor
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Sikistirilmis XML'in acilmis boyut siniri (zip bombasina karsi). */
    private const MAX_XML_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly XlsxSheetReader $sheets) {}

    /** Desteklenen uzanti mi? */
    public static function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['docx', 'xlsx', 'xlsm'], true);
    }

    /**
     * @throws RuntimeException dosya okunamazsa
     */
    public function extract(string $path, string $extension, int $maxCharacters): string
    {
        $text = match (strtolower($extension)) {
            'docx' => $this->word($path),
            'xlsx', 'xlsm' => $this->workbook($path),
            default => throw new RuntimeException('unsupported'),
        };

        $text = trim((string) preg_replace(["/[ \t]+\n/u", "/\n{3,}/u"], ["\n", "\n\n"], $text));

        return mb_substr($text, 0, $maxCharacters);
    }

    private function word(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('docx');
        }

        try {
            $stat = $zip->statName('word/document.xml');

            if ($stat === false || (int) $stat['size'] > self::MAX_XML_BYTES) {
                throw new RuntimeException('docx');
            }

            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('docx');
        }

        $dom = new DOMDocument;

        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('docx');
        }

        $body = $dom->getElementsByTagNameNS(self::WORD_NS, 'body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new RuntimeException('docx');
        }

        $lines = [];

        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::WORD_NS) {
                continue;
            }

            if ($node->localName === 'p') {
                $lines[] = $this->paragraph($node);
            } elseif ($node->localName === 'tbl') {
                foreach ($this->tableRows($node) as $row) {
                    $lines[] = $row;
                }

                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function tableRows(DOMElement $table): array
    {
        $rows = [];

        foreach ($table->childNodes as $row) {
            if (! $row instanceof DOMElement || $row->localName !== 'tr') {
                continue;
            }

            $cells = [];

            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || $cell->localName !== 'tc') {
                    continue;
                }

                $parts = [];

                foreach ($cell->getElementsByTagNameNS(self::WORD_NS, 'p') as $paragraph) {
                    $parts[] = $this->paragraph($paragraph);
                }

                $cells[] = trim(implode(' ', array_filter($parts, static fn (string $part): bool => trim($part) !== '')));
            }

            $rows[] = implode("\t", $cells);
        }

        return $rows;
    }

    private function paragraph(DOMNode $paragraph): string
    {
        $text = '';

        foreach ($paragraph->getElementsByTagNameNS(self::WORD_NS, '*') as $element) {
            $text .= match ($element->localName) {
                't' => $element->textContent,
                'tab' => "\t",
                'br', 'cr' => "\n",
                default => '',
            };
        }

        return $text;
    }

    private function workbook(string $path): string
    {
        try {
            $sheets = $this->sheets->read($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('xlsx', previous: $exception);
        }

        $lines = [];

        foreach ($sheets as $name => $rows) {
            $lines[] = '# '.$name;

            foreach ($rows as $row) {
                $cells = $row['cells'] ?? [];

                if ($cells === []) {
                    continue;
                }

                uksort($cells, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
                $values = array_map(static fn (string $value): string => trim((string) preg_replace('/\s+/u', ' ', $value)), $cells);

                if (implode('', $values) !== '') {
                    $lines[] = implode("\t", $values);
                }
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
