<?php

declare(strict_types=1);

namespace App\Support\Procurement;

use App\Enums\Procurement\CostLineType;
use App\Enums\Procurement\PriceState;
use App\Exceptions\Procurement\CostListFormatException;
use App\Infrastructure\Database\Spreadsheet\XlsxSheetReader;
use Throwable;

/**
 * Maliyet Excel'i -> maliyet kalemleri (D-187 / D-189). Bicim, kullanicinin
 * gonderdigi Kartal RES Hibrit GES maliyet dosyasidir:
 *
 * - "Birim_Fiyat_..." sayfasi (Urun/Hizmet): A sutunu harf = bolum (Insaat),
 *   "A.1" = grup (Saha Duzeltme), sayi = kalem. B aciklama, C not, F miktar,
 *   G birim, O birim maliyet, P toplam maliyet, I toplam satis (teklif para
 *   biriminde), T / U Icmal kapsam ve alt kapsam adi. Satir 2: P toplam
 *   maliyet, I toplam satis (mutabakat).
 * - "Idari Kadro ve Genel Giderler" sayfasi: "A.1" Idari kadro, diger "A.n"
 *   genel gider gruplari; kalemde B aciklama, C adet, D sure, E birim fiyat
 *   (TL), G toplam (TL), H toplam (teklif kuru), I not. 72. satirdan sonrasi
 *   (sozlesme ve eskalasyon) okunmaz.
 * - Satir rengi fiyat durumudur (kullanici 10 Ekim 2026): yesil kesin, sari
 *   netlesmedi.
 *
 * Kullanicinin saydigi sayfalar disindakiler (Icmal Liste, Nakit Akis,
 * Deviasyon, KOSK ...) okunmaz. Yazmaz; yalniz okur.
 */
final class CostExcelParser
{
    public const PRODUCT_SHEET_PREFIX = 'Birim_Fiyat';

    public const OVERHEAD_SHEET = 'İdari Kadro ve Genel Giderler';

    private const FIRM_FILL = 'FF92D050';

    private const OPEN_FILL = 'FFFFFF00';

    private const OVERHEAD_LAST_ROW = 71;

    public function __construct(private readonly XlsxSheetReader $reader) {}

    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     total_cost: float,
     *     total_sale: float|null,
     *     excel_total_cost: float|null,
     *     excel_total_sale: float|null,
     *     sheets: list<string>,
     * }
     */
    public function parse(string $path, string $listCurrency = 'USD'): array
    {
        try {
            $names = $this->reader->sheetNames($path);
        } catch (Throwable) {
            throw CostListFormatException::make();
        }

        $productSheet = null;

        foreach ($names as $name) {
            if (str_starts_with($name, self::PRODUCT_SHEET_PREFIX)) {
                $productSheet = $name;
                break;
            }
        }

        if ($productSheet === null) {
            throw CostListFormatException::make();
        }

        $sheets = array_values(array_filter([$productSheet, in_array(self::OVERHEAD_SHEET, $names, true) ? self::OVERHEAD_SHEET : null]));
        $data = $this->reader->read($path, $sheets);
        [$products, $excelCost, $excelSale] = $this->products($productSheet, $data[$productSheet] ?? [], $listCurrency);
        [$overhead, $overheadInList] = $this->overhead($data[self::OVERHEAD_SHEET] ?? []);

        $lines = [...$products, ...$overhead];
        $order = 0;

        foreach ($lines as &$line) {
            $line['sort_order'] = ++$order;
        }
        unset($line);

        $productCost = array_sum(array_map(static fn (array $line): float => (float) $line['total_cost'], $products));
        $productSale = array_sum(array_map(static fn (array $line): float => (float) ($line['total_sale'] ?? 0), $products));

        return [
            'lines' => $lines,
            'total_cost' => $productCost + $overheadInList,
            'total_sale' => $productSale > 0 ? $productSale : null,
            'excel_total_cost' => $excelCost,
            'excel_total_sale' => $excelSale,
            'sheets' => $sheets,
        ];
    }

    /**
     * @param  list<array{r: int, fill: string, cells: array<string, string>}>  $rows
     * @return array{0: list<array<string, mixed>>, 1: float|null, 2: float|null}
     */
    private function products(string $sheet, array $rows, string $currency): array
    {
        $lines = [];
        $section = ['no' => null, 'name' => null];
        $group = null;
        $excelCost = null;
        $excelSale = null;
        $sectionTitles = [];

        foreach ($rows as $row) {
            $cells = $row['cells'];
            $number = $row['r'];

            if ($number <= 3) {
                if ($number === 2) {
                    $excelCost = self::num($cells['P'] ?? null);
                    $excelSale = self::num($cells['I'] ?? null);
                }

                continue;
            }

            $code = $cells['A'] ?? '';
            $hasCost = array_key_exists('P', $cells) || array_key_exists('O', $cells);

            if (preg_match('/^[A-Z]$/', $code) === 1 && ! $hasCost) {
                $section = ['no' => $code, 'name' => $cells['B'] ?? $code];
                $group = null;

                continue;
            }

            if (preg_match('/^[A-Z](\.\d+)+$/', $code) === 1) {
                $group = $cells['B'] ?? $code;

                if ($hasCost) {
                    $lines[] = $this->productLine($sheet, $row, $section, null, $code, $currency);
                }

                continue;
            }

            if (preg_match('/^\d+$/', $code) === 1 && filled($cells['B'] ?? null)) {
                $line = $this->productLine($sheet, $row, $section, $group, $code, $currency);
                $lines[] = $line;

                // Bolumun Icmal'deki adi (T) varsa bolum adi odur ("Insaat").
                if (filled($cells['T'] ?? null) && $section['no'] !== null) {
                    $sectionTitles[$section['no']] ??= (string) $cells['T'];
                }
            }
        }

        foreach ($lines as &$line) {
            if (isset($sectionTitles[$line['section_no']])) {
                $line['section_name'] = $sectionTitles[$line['section_no']];
            }
        }
        unset($line);

        return [$lines, $excelCost, $excelSale];
    }

    /**
     * @param  array{r: int, fill: string, cells: array<string, string>}  $row
     * @param  array{no: string|null, name: string|null}  $section
     * @return array<string, mixed>
     */
    private function productLine(string $sheet, array $row, array $section, ?string $group, string $code, string $currency): array
    {
        $cells = $row['cells'];

        return [
            'line_type' => CostLineType::ProductService->value,
            'excel_sheet' => $sheet,
            'excel_row' => $row['r'],
            'section_no' => $section['no'],
            'section_name' => $section['name'],
            'group_name' => filled($cells['U'] ?? null) ? (string) $cells['U'] : $group,
            'line_no' => $code,
            'description' => mb_substr((string) ($cells['B'] ?? $code), 0, 500),
            'quantity' => self::num($cells['F'] ?? null),
            'unit' => self::text($cells['G'] ?? null, 40),
            'duration' => null,
            'unit_cost' => self::num($cells['O'] ?? null),
            'currency_code' => $currency,
            'total_cost' => self::num($cells['P'] ?? null) ?? 0.0,
            'total_sale' => self::num($cells['I'] ?? null),
            'price_state' => self::priceState($row['fill']),
            'note' => self::text($cells['C'] ?? null, 2000),
        ];
    }

    /**
     * @param  list<array{r: int, fill: string, cells: array<string, string>}>  $rows
     * @return array{0: list<array<string, mixed>>, 1: float}
     */
    private function overhead(array $rows): array
    {
        $lines = [];
        $groupNo = null;
        $groupName = null;
        $inList = 0.0;

        foreach ($rows as $row) {
            $cells = $row['cells'];

            if ($row['r'] <= 2 || $row['r'] > self::OVERHEAD_LAST_ROW) {
                continue;
            }

            $code = $cells['A'] ?? '';

            if (preg_match('/^A\.\d+$/', $code) === 1) {
                $groupNo = $code;
                $groupName = $cells['B'] ?? $code;

                continue;
            }

            if ($groupNo === null || preg_match('/^\d+$/', $code) !== 1 || ! filled($cells['B'] ?? null)) {
                continue;
            }

            $type = $groupNo === 'A.1' ? CostLineType::AdminStaff : CostLineType::GeneralExpense;
            $inList += self::num($cells['H'] ?? null) ?? 0.0;

            $lines[] = [
                'line_type' => $type->value,
                'excel_sheet' => self::OVERHEAD_SHEET,
                'excel_row' => $row['r'],
                'section_no' => $groupNo,
                'section_name' => $groupName,
                'group_name' => null,
                'line_no' => $code,
                'description' => mb_substr((string) $cells['B'], 0, 500),
                'quantity' => self::num($cells['C'] ?? null),
                'unit' => null,
                'duration' => self::num($cells['D'] ?? null),
                'unit_cost' => self::num($cells['E'] ?? null),
                'currency_code' => 'TRY',
                'total_cost' => self::num($cells['G'] ?? null) ?? 0.0,
                'total_sale' => null,
                'price_state' => self::priceState($row['fill']),
                'note' => self::text($cells['I'] ?? null, 2000),
            ];
        }

        return [$lines, $inList];
    }

    private static function priceState(string $fill): ?string
    {
        return match (strtoupper($fill)) {
            self::FIRM_FILL => PriceState::Firm->value,
            self::OPEN_FILL => PriceState::Open->value,
            default => null,
        };
    }

    private static function num(?string $value): ?float
    {
        return $value !== null && $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private static function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
