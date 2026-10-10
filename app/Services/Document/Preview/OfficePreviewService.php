<?php

declare(strict_types=1);

namespace App\Services\Document\Preview;

use App\Models\Document\FileObject;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;
use ZipArchive;

/**
 * Excel / CSV / Word onizleme (D-186, 9 Ekim 2026 kullanici istegi: "xlsx, docx
 * gibi belgelerde indirilmeden goruntulenebiliyor mu ... pdf gibi
 * goruntulenebilir olabilir. Indirmek zorunda birakilmasin"; ozellik
 * documents.office_preview).
 *
 * Yeni paket yok: tablolar projede zaten bulunan OpenSpout ile (xlsx, xlsm,
 * ods, csv), Word belgesi PHP'nin ZipArchive + DOM'u ile (docx:
 * word/document.xml'den basliklar, paragraflar, kalin / italik yazilar,
 * madde isaretleri ve tablolar) okunur. Eski ikili .xls / .doc desteklenmez
 * (indirilir). Sinirlar: ilk 10 sayfa adi, sayfa basina 500 satir ve 50 sutun,
 * Word'de 3000 blok; dosya 25 MB'tan buyukse onizleme yok.
 *
 * Cikti sablonsuz, kendi icinde tam bir HTML sayfasidir (yeni Blade / JS yok);
 * butun metin kacislanir, sayfa betik calistirmaz (denetleyici CSP basligi
 * script-src vermez). Yetki karari cagiran denetleyicidedir.
 */
final class OfficePreviewService
{
    /** @var list<string> */
    private const SHEET_EXTENSIONS = ['xlsx', 'xlsm', 'ods', 'csv'];

    private const WORD_EXTENSION = 'docx';

    private const MAX_BYTES = 25 * 1024 * 1024;

    private const MAX_SHEETS = 10;

    private const MAX_ROWS = 500;

    private const MAX_COLUMNS = 50;

    private const MAX_BLOCKS = 3000;

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Dosya adi onizlenebilen turde mi (uzantiya gore). */
    public static function supports(?string $fileName): bool
    {
        $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return in_array($extension, [...self::SHEET_EXTENSIONS, self::WORD_EXTENSION], true)
            && ($extension !== self::WORD_EXTENSION || class_exists(ZipArchive::class));
    }

    /**
     * Onizleme sayfasinin HTML'i. $sheet: tablo dosyasinda gosterilecek sayfa (0'dan).
     */
    public function render(FileObject $file, int $sheet, string $downloadUrl): string
    {
        $name = (string) $file->original_name;
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ((int) $file->byte_size > self::MAX_BYTES) {
            return $this->page($name, $downloadUrl, [], null, '<p class="msg">'.e(__('document.preview.too_large')).'</p>');
        }

        $local = null;

        try {
            [$path, $local] = $this->localPath($file);

            if ($extension === self::WORD_EXTENSION) {
                [$html, $truncated] = $this->word($path);

                return $this->page($name, $downloadUrl, [], null, $html, $truncated ? __('document.preview.paragraph_limit') : null);
            }

            $sheet = min(max($sheet, 0), self::MAX_SHEETS - 1);
            [$names, $rows, $truncated] = $this->sheet($path, $extension, $sheet);

            return $this->page(
                $name,
                $downloadUrl,
                $names,
                min(max($sheet, 0), max(count($names) - 1, 0)),
                $this->table($rows),
                $truncated ? __('document.preview.sheet_limit', ['rows' => self::MAX_ROWS, 'columns' => self::MAX_COLUMNS]) : null,
            );
        } catch (Throwable) {
            return $this->page($name, $downloadUrl, [], null, '<p class="msg">'.e(__('document.preview.unavailable')).'</p>');
        } finally {
            if ($local !== null && is_file($local)) {
                @unlink($local);
            }
        }
    }

    /**
     * Dosyanin yerel yolu; yerel olmayan diskte gecici kopya (ikinci deger: silinecek kopya).
     *
     * @return array{0: string, 1: string|null}
     */
    private function localPath(FileObject $file): array
    {
        /** @var Filesystem $disk */
        $disk = Storage::disk($file->storage_disk ?: 'local');
        $key = (string) $file->storage_key;

        if ($disk instanceof FilesystemAdapter) {
            try {
                $path = $disk->path($key);

                if (is_file($path)) {
                    return [$path, null];
                }
            } catch (Throwable) {
                // Yerel olmayan disk: asagida gecici kopya.
            }
        }

        $extension = strtolower(pathinfo((string) $file->original_name, PATHINFO_EXTENSION));
        $temp = tempnam(sys_get_temp_dir(), 'kcpv');

        if ($temp === false) {
            throw new \RuntimeException('temp');
        }

        $target = $temp.'.'.$extension;
        @unlink($temp);
        $stream = $disk->readStream($key);

        if (! is_resource($stream)) {
            throw new \RuntimeException('stream');
        }

        $out = fopen($target, 'wb');

        if ($out === false) {
            throw new \RuntimeException('temp');
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return [$target, $target];
    }

    /**
     * Tablo dosyasi: sayfa adlari, secili sayfanin satirlari, kesildi mi.
     *
     * @return array{0: list<string>, 1: list<list<string>>, 2: bool}
     */
    private function sheet(string $path, string $extension, int $selected): array
    {
        $reader = $this->reader($path, $extension);
        $reader->open($path);
        $names = [];
        $rows = [];
        $truncated = false;
        $width = 0;

        try {
            $index = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($index >= self::MAX_SHEETS) {
                    break;
                }

                $names[] = $extension === 'csv' ? '' : (string) $sheet->getName();

                if ($index === $selected) {
                    foreach ($sheet->getRowIterator() as $row) {
                        if (count($rows) >= self::MAX_ROWS) {
                            $truncated = true;

                            break;
                        }

                        $cells = array_map(fn (mixed $value): string => $this->cellText($value), $row->toArray());

                        if (count($cells) > self::MAX_COLUMNS) {
                            $truncated = true;
                            $cells = array_slice($cells, 0, self::MAX_COLUMNS);
                        }

                        // Sondaki bos hucreler sutun sayisina girmez.
                        for ($last = count($cells) - 1; $last >= 0 && $cells[$last] === ''; $last--);
                        $width = max($width, $last + 1);
                        $rows[] = $cells;
                    }
                }

                $index++;
            }
        } finally {
            $reader->close();
        }

        foreach ($rows as $i => $cells) {
            $rows[$i] = array_pad(array_slice($cells, 0, $width), $width, '');
        }

        return [$names, $rows, $truncated];
    }

    private function reader(string $path, string $extension): ReaderInterface
    {
        if ($extension === 'csv') {
            $options = new CsvOptions;
            $sample = (string) @file_get_contents($path, false, null, 0, 65536);
            $firstLine = strtok($sample, "\n") ?: '';
            $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
            arsort($counts);
            $options->FIELD_DELIMITER = (string) array_key_first($counts);

            // Turkce Excel'in kaydettigi CSV cogunlukla Windows-1254'tur.
            if ($sample !== '' && ! mb_check_encoding($sample, 'UTF-8')) {
                $options->ENCODING = 'Windows-1254';
            }

            return new CsvReader($options);
        }

        if ($extension === 'ods') {
            return new OdsReader;
        }

        $options = new XlsxOptions;
        $options->SHOULD_FORMAT_DATES = true;

        return new XlsxReader($options);
    }

    private function cellText(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('d.m.Y'),
            is_bool($value) => $value ? '✓' : '✗',
            is_int($value) => (string) $value,
            is_float($value) => (string) Number::format($value, maxPrecision: 6, locale: 'tr'),
            is_scalar($value) => trim((string) $value),
            $value instanceof \Stringable => trim((string) $value),
            default => '',
        };
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function table(array $rows): string
    {
        if ($rows === [] || $rows[0] === []) {
            return '<p class="msg">'.e(__('document.preview.empty')).'</p>';
        }

        $html = '<div class="scroll"><table><thead><tr><th class="n"></th>';

        foreach (array_keys($rows[0]) as $column) {
            $html .= '<th>'.e(self::columnName($column)).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $number => $cells) {
            $html .= '<tr><td class="n">'.($number + 1).'</td>';

            foreach ($cells as $cell) {
                $html .= '<td>'.nl2br(e($cell)).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table></div>';
    }

    /** 0 -> A, 25 -> Z, 26 -> AA. */
    private static function columnName(int $index): string
    {
        $name = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + (($index - 1) % 26)).$name;
        }

        return $name;
    }

    /**
     * Word belgesi: basliklar, paragraflar, kalin / italik, madde isaretleri ve tablolar.
     *
     * @return array{0: string, 1: bool}
     */
    private function word(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('zip');
        }

        try {
            $stat = $zip->statName('word/document.xml');

            // Sikistirma bombasina karsi: acilmis XML de sinirli.
            if ($stat === false || (int) $stat['size'] > 4 * self::MAX_BYTES) {
                throw new \RuntimeException('size');
            }

            $xml = (string) $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        $document = new DOMDocument;

        // Dis varlik yuklenmez (LIBXML_NONET); varliklar yerine konmaz (NOENT yok).
        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('xml');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NS);
        $body = $xpath->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            return ['<p class="msg">'.e(__('document.preview.empty')).'</p>', false];
        }

        $html = '';
        $blocks = 0;
        $truncated = false;

        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::WORD_NS) {
                continue;
            }

            if (++$blocks > self::MAX_BLOCKS) {
                $truncated = true;

                break;
            }

            $html .= match ($node->localName) {
                'p' => $this->paragraph($xpath, $node),
                'tbl' => $this->wordTable($xpath, $node),
                default => '',
            };
        }

        return ['<article class="doc">'.($html !== '' ? $html : '<p class="msg">'.e(__('document.preview.empty')).'</p>').'</article>', $truncated];
    }

    private function paragraph(DOMXPath $xpath, DOMElement $paragraph, bool $inline = false): string
    {
        $text = $this->runs($xpath, $paragraph);

        if ($inline) {
            return $text;
        }

        $style = (string) ($xpath->query('w:pPr/w:pStyle/@w:val', $paragraph)->item(0)?->nodeValue ?? '');
        $isList = $xpath->query('w:pPr/w:numPr', $paragraph)->length > 0;

        if ($text === '') {
            return '<p class="empty"></p>';
        }

        if (preg_match('/^(?:Title|KonuBa)/i', $style) === 1) {
            return '<h1>'.$text.'</h1>';
        }

        if (preg_match('/^(?:Heading|Balk|Baslik|Başlık)\s*([1-6])/iu', $style, $match) === 1) {
            $level = min(6, (int) $match[1] + 1);

            return '<h'.$level.'>'.$text.'</h'.$level.'>';
        }

        return $isList ? '<p class="li">• '.$text.'</p>' : '<p>'.$text.'</p>';
    }

    private function runs(DOMXPath $xpath, DOMElement $paragraph): string
    {
        $html = '';

        foreach ($xpath->query('.//w:r', $paragraph) as $run) {
            if (! $run instanceof DOMElement) {
                continue;
            }

            $text = '';

            foreach ($run->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                $text .= match ($child->localName) {
                    't' => e($child->textContent),
                    'tab' => '&emsp;',
                    'br', 'cr' => '<br>',
                    default => '',
                };
            }

            if ($text === '') {
                continue;
            }

            if (self::flag($xpath, $run, 'b')) {
                $text = '<strong>'.$text.'</strong>';
            }

            if (self::flag($xpath, $run, 'i')) {
                $text = '<em>'.$text.'</em>';
            }

            $html .= $text;
        }

        return $html;
    }

    private static function flag(DOMXPath $xpath, DOMElement $run, string $name): bool
    {
        $node = $xpath->query('w:rPr/w:'.$name, $run)->item(0);

        if (! $node instanceof DOMElement) {
            return false;
        }

        $value = strtolower($node->getAttributeNS(self::WORD_NS, 'val'));

        return ! in_array($value, ['0', 'false', 'off'], true);
    }

    private function wordTable(DOMXPath $xpath, DOMElement $table): string
    {
        $html = '<div class="scroll"><table class="word">';

        foreach ($xpath->query('w:tr', $table) as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $html .= '<tr>';

            foreach ($xpath->query('w:tc', $row) as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $lines = [];

                foreach ($xpath->query('w:p', $cell) as $paragraph) {
                    if ($paragraph instanceof DOMElement) {
                        $lines[] = $this->paragraph($xpath, $paragraph, inline: true);
                    }
                }

                $span = (int) ($xpath->query('w:tcPr/w:gridSpan/@w:val', $cell)->item(0)?->nodeValue ?? 1);
                $html .= '<td'.($span > 1 ? ' colspan="'.$span.'"' : '').'>'.implode('<br>', array_filter($lines, static fn (string $line): bool => $line !== '')).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</table></div>';
    }

    /**
     * Tam sayfa: ust serit (dosya adi, indir), sayfa sekmeleri (?sheet=N), not, icerik.
     *
     * @param  list<string>  $sheets
     */
    private function page(string $name, string $downloadUrl, array $sheets, ?int $active, string $content, ?string $limit = null): string
    {
        $tabs = '';

        if (count($sheets) > 1) {
            $tabs = '<nav class="tabs">';

            foreach ($sheets as $index => $sheet) {
                $label = $sheet !== '' ? $sheet : (string) ($index + 1);
                $tabs .= $index === $active
                    ? '<span class="tab on">'.e($label).'</span>'
                    : '<a class="tab" href="?sheet='.$index.'">'.e($label).'</a>';
            }

            $tabs .= '</nav>';
        }

        $notes = '<p class="note">'.e(__('document.preview.note')).($limit !== null ? ' '.e($limit) : '').'</p>';

        return '<!doctype html><html lang="'.e(app()->getLocale()).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.e(__('document.preview.title', ['name' => $name])).'</title>'
            .'<style>'.self::STYLE.'</style></head><body>'
            .'<header><span class="name" title="'.e($name).'">'.e($name).'</span>'
            .'<a class="dl" href="'.e($downloadUrl).'">'.e(__('document.preview.download')).'</a></header>'
            .$tabs.'<main>'.$notes.$content.'</main></body></html>';
    }

    private const STYLE = <<<'CSS'
        :root { color-scheme: light dark; --bg: #f8fafc; --card: #fff; --line: #e2e8f0; --text: #0f172a; --muted: #64748b; --head: #f1f5f9; --accent: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1120; --card: #111827; --line: #1f2937; --text: #e5e7eb; --muted: #94a3b8; --head: #1f2937; --accent: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        header { position: sticky; top: 0; z-index: 2; display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 10px 16px; background: var(--card); border-bottom: 1px solid var(--line); }
        header .name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        header .dl { flex-shrink: 0; padding: 5px 12px; border-radius: 8px; border: 1px solid var(--line); color: var(--text); text-decoration: none; font-weight: 600; font-size: 13px; }
        header .dl:hover { background: var(--head); }
        .tabs { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px 16px 0; }
        .tab { padding: 4px 10px; border-radius: 6px 6px 0 0; border: 1px solid var(--line); border-bottom: 0; color: var(--muted); text-decoration: none; font-size: 13px; background: var(--head); }
        .tab.on { color: var(--text); background: var(--card); font-weight: 600; }
        main { padding: 12px 16px 32px; }
        .note { margin: 0 0 10px; color: var(--muted); font-size: 12px; }
        .msg { padding: 24px; text-align: center; color: var(--muted); background: var(--card); border: 1px solid var(--line); border-radius: 10px; }
        .scroll { overflow: auto; max-width: 100%; background: var(--card); border: 1px solid var(--line); border-radius: 10px; }
        table { border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid var(--line); padding: 4px 8px; vertical-align: top; white-space: pre-wrap; max-width: 420px; }
        thead th { position: sticky; top: 0; background: var(--head); color: var(--muted); font-weight: 600; }
        td.n, th.n { background: var(--head); color: var(--muted); text-align: right; min-width: 36px; }
        .doc { max-width: 860px; margin: 0 auto; padding: 24px 32px; background: var(--card); border: 1px solid var(--line); border-radius: 10px; }
        .doc p { margin: 0 0 8px; }
        .doc p.empty { min-height: 8px; }
        .doc p.li { padding-left: 12px; }
        .doc h1, .doc h2, .doc h3, .doc h4, .doc h5, .doc h6 { margin: 16px 0 8px; line-height: 1.3; }
        .doc .scroll { margin: 8px 0 12px; }
        .doc table.word td { white-space: normal; }
        CSS;
}
