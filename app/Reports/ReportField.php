<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * Taslak alani tanimi (D-86). Taslak siniflari alanlarini bu nesneyle
 * bildirir; form bileseni ve goruntuleme girdisi ReportFieldComponents
 * tarafindan bu tanimdan uretilir. Etiket, yardim metni ve secenek adlari
 * dil dosyasindan gelir: report.templates.{taslak}.fields.{alan},
 * report.templates.{taslak}.help.{alan}, report.templates.{taslak}.options.{alan}.{deger}.
 */
final class ReportField
{
    public const TEXT = 'text';

    public const LONG_TEXT = 'long_text';

    public const INTEGER = 'integer';

    public const DECIMAL = 'decimal';

    public const PERCENT = 'percent';

    public const RATING = 'rating';

    public const CHOICE = 'choice';

    /** Satir satir is listesi: formda cok satirli metin, raporda tablo. */
    public const LINES = 'lines';

    public const KEY_VALUE = 'key_value';

    public const BOOLEAN = 'boolean';

    public const DATE = 'date';

    public bool $required = false;

    /** @var list<string> */
    public array $options = [];

    public ?float $min = null;

    public ?float $max = null;

    public ?string $suffix = null;

    public int $rows = 3;

    /** Gonderimde report_metrics'e yazilir. */
    public bool $metric = false;

    public ?string $unit = null;

    /** Rapor ozetinde kullanilir. */
    public bool $summary = false;

    /** @var array<string, int|string>|null */
    public ?array $span = null;

    private function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {}

    public static function text(string $name): self
    {
        return new self($name, self::TEXT);
    }

    public static function longText(string $name, int $rows = 4): self
    {
        $field = new self($name, self::LONG_TEXT);
        $field->rows = $rows;

        return $field;
    }

    /** Her satiri bir is olan plan alani (yarin plani, hafta plani). */
    public static function lines(string $name, int $rows = 3): self
    {
        $field = new self($name, self::LINES);
        $field->rows = $rows;

        return $field;
    }

    public static function integer(string $name): self
    {
        return new self($name, self::INTEGER);
    }

    public static function decimal(string $name): self
    {
        return new self($name, self::DECIMAL);
    }

    public static function percent(string $name): self
    {
        $field = new self($name, self::PERCENT);
        $field->min = 0;
        $field->max = 100;
        $field->suffix = '%';
        $field->unit = '%';

        return $field;
    }

    /** 1-5 puanlama. */
    public static function rating(string $name): self
    {
        $field = new self($name, self::RATING);
        $field->min = 1;
        $field->max = 5;
        $field->unit = 'puan';

        return $field;
    }

    /**
     * @param  list<string>  $options
     */
    public static function choice(string $name, array $options): self
    {
        $field = new self($name, self::CHOICE);
        $field->options = $options;

        return $field;
    }

    public static function keyValue(string $name): self
    {
        return new self($name, self::KEY_VALUE);
    }

    public static function boolean(string $name): self
    {
        return new self($name, self::BOOLEAN);
    }

    public static function date(string $name): self
    {
        return new self($name, self::DATE);
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    /** Sayisal alani KPI olarak isaretler; birim metni istege bagli. */
    public function metric(?string $unit = null): self
    {
        $this->metric = true;

        if ($unit !== null) {
            $this->unit = $unit;
        }

        return $this;
    }

    public function min(float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function suffix(string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function summary(): self
    {
        $this->summary = true;

        return $this;
    }

    /**
     * @param  array<string, int|string>  $span
     */
    public function span(array $span): self
    {
        $this->span = $span;

        return $this;
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, [self::INTEGER, self::DECIMAL, self::PERCENT, self::RATING], true);
    }

    public function isLongText(): bool
    {
        return $this->type === self::LONG_TEXT;
    }
}
