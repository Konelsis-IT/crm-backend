<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportPresentation;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sistem verileri raporu: serbest olcum listesi (ad => deger) + gozlem ve
 * anormallikler. Sayisal olcumler metrik olarak yazilir (kod = olcum adinin
 * slug'i).
 */
final class SystemDataReportTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'system_data';
    }

    public function kind(): ReportKind
    {
        return ReportKind::System;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Numeric;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Range;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::text('data_source')->required(),
            ReportField::keyValue('measurements'),
            ReportField::longText('observations', 4)->summary(),
            ReportField::longText('anomalies', 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $metrics = parent::metrics($payload, $items);

        foreach ((array) ($payload['measurements'] ?? []) as $key => $value) {
            $code = Str::limit(Str::slug((string) $key, '_'), 64, '');
            $number = is_string($value) ? self::parseNumber($value) : $value;

            if ($code !== '' && is_numeric($number)) {
                $metrics[$code] = (float) $number;
            }
        }

        return $metrics;
    }

    /** "1.250,50" / "1,250.50" / "1250,5" gibi girdileri sayiya cevirir; sayi degilse oldugu gibi doner. */
    private static function parseNumber(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            // Son ayirici ondalik ayiracidir; digeri binlik.
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(['.', ','], ['', '.'], $value)
                : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }
}
