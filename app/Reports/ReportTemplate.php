<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportReviewMode;
use App\Enums\Report\ReportSubjectKind;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Rapor taslagi (D-86, kullanici karari 12 Eylul 2026): taslaklar arayuzden
 * degil kodda tanimlanir. Bir taslak; turunu, bicimini (pano / yorumsal /
 * sayisal / degerlendirme), bagli oldugu konu turunu, donem bicimini, kimin
 * yazabilecegini, kimin inceleyecegini ve alanlarini bildirir. Form ve
 * goruntuleme bu tanimdan uretilir; secilen taslak raporu sekillendirir.
 *
 * Yeni taslak eklemek: bu sinifi genisletip ReportTemplateRegistry::TEMPLATES
 * listesine eklemek ve lang/{tr,en}/report.php `templates.{kod}` bolumunu
 * yazmak yeter.
 */
abstract class ReportTemplate
{
    /** @var list<ReportField>|null */
    private ?array $fieldCache = null;

    abstract public function code(): string;

    abstract public function kind(): ReportKind;

    abstract public function presentation(): ReportPresentation;

    /**
     * @return list<ReportField>
     */
    abstract protected function fields(): array;

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::None;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::None;
    }

    /** Donem girilmesi zorunlu mu? Varsayilan: donem bicimi varsa zorunlu. */
    public function periodRequired(): bool
    {
        return $this->periodMode() !== ReportPeriodMode::None;
    }

    public function reviewMode(): ReportReviewMode
    {
        return ReportReviewMode::None;
    }

    public function authorRule(): ReportAuthorRule
    {
        return ReportAuthorRule::Anyone;
    }

    /** Gizli rapor: yalniz yazar, inceleyen, konu personelin yoneticileri ve yetkili gorur. */
    public function isConfidential(): bool
    {
        return false;
    }

    /**
     * Personel bu taslagi Raporlar > Yeni rapor formundan secebilir mi? Is
     * panosunun urettigi taslaklar (kontrol matrisi, pano dondurmasi) yalniz
     * kendi sayfalarindan yazilir (B36, D-115).
     */
    public function isManualEntry(): bool
    {
        return true;
    }

    /** Pano is kalemleri (report_items) bu taslakta var mi? */
    public function hasItems(): bool
    {
        return $this->presentation() === ReportPresentation::Board;
    }

    public function icon(): Heroicon
    {
        return $this->kind()->getIcon();
    }

    public function name(): string
    {
        return $this->translate('name') ?? Str::headline($this->code());
    }

    public function description(): ?string
    {
        return $this->translate('description');
    }

    /**
     * @return list<ReportField>
     */
    public function fieldList(): array
    {
        return $this->fieldCache ??= $this->fields();
    }

    public function field(string $name): ?ReportField
    {
        foreach ($this->fieldList() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function label(string $fieldName): string
    {
        return $this->translate('fields.'.$fieldName) ?? Str::headline($fieldName);
    }

    public function help(string $fieldName): ?string
    {
        return $this->translate('help.'.$fieldName);
    }

    public function optionLabel(string $fieldName, string $value): string
    {
        return $this->translate('options.'.$fieldName.'.'.$value) ?? Str::headline($value);
    }

    /**
     * Metrik etiketi: alan etiketi, taslagin hesaplanan metrik etiketi ya da
     * ortak metrik etiketi.
     */
    public function metricLabel(string $code): string
    {
        if ($this->field($code) !== null) {
            return $this->label($code);
        }

        $label = $this->translate('metrics.'.$code);

        if ($label !== null) {
            return $label;
        }

        $shared = 'report.metrics.'.$code;

        return Lang::has($shared) ? __($shared) : Str::headline($code);
    }

    public function metricUnit(string $code): ?string
    {
        return $this->field($code)?->unit ?? ($this->extraMetricUnits()[$code] ?? null);
    }

    /**
     * Form bilesenleri (payload bolumune konur).
     *
     * @return array<int, Component>
     */
    public function formComponents(): array
    {
        return array_map(
            fn (ReportField $field): Component => ReportFieldComponents::form($this, $field),
            $this->fieldList(),
        );
    }

    /**
     * Goruntuleme girdileri (`payload.{alan}`).
     *
     * @return array<int, Component>
     */
    public function infolistEntries(): array
    {
        return array_map(
            fn (ReportField $field): Component => ReportFieldComponents::entry($this, $field),
            $this->fieldList(),
        );
    }

    /**
     * KPI degerleri: metrik isaretli sayisal alanlar. Alt sinif hesaplanan
     * metrikleri ekleyebilir (parent::metrics() + ...).
     *
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, \App\Models\Report\ReportItem>|null  $items
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $metrics = [];

        foreach ($this->fieldList() as $field) {
            if (! $field->metric || ! $field->isNumeric()) {
                continue;
            }

            $value = $payload[$field->name] ?? null;

            if (is_numeric($value)) {
                $metrics[$field->name] = (float) $value;
            }
        }

        return $metrics;
    }

    /**
     * Rapor ozeti: `summary()` isaretli alan, yoksa ilk uzun metin.
     *
     * @param  array<string, mixed>  $payload
     */
    public function summary(array $payload): ?string
    {
        $candidates = array_filter($this->fieldList(), fn (ReportField $field): bool => $field->summary);

        if ($candidates === []) {
            $candidates = array_filter($this->fieldList(), fn (ReportField $field): bool => $field->isLongText());
        }

        foreach ($candidates as $field) {
            $value = $payload[$field->name] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return Str::limit(Str::squish($value), 500, '…');
            }
        }

        return null;
    }

    /** Baslik bos birakilirsa: taslak adi + konu / donem. */
    public function defaultTitle(?string $subjectLabel, ?Carbon $periodStart, ?Carbon $periodEnd): string
    {
        $parts = [$this->name()];

        if (filled($subjectLabel)) {
            $parts[] = $subjectLabel;
        }

        if ($periodStart !== null) {
            $parts[] = match ($this->periodMode()) {
                ReportPeriodMode::Day => $periodStart->format('d.m.Y'),
                ReportPeriodMode::Week => __('report.values.week_of', ['date' => $periodStart->format('d.m.Y')]),
                ReportPeriodMode::Month => $periodStart->translatedFormat('F Y'),
                default => $periodEnd !== null && ! $periodStart->isSameDay($periodEnd)
                    ? $periodStart->format('d.m.Y').' – '.$periodEnd->format('d.m.Y')
                    : $periodStart->format('d.m.Y'),
            };
        }

        return Str::limit(implode(' — ', $parts), 200, '');
    }

    /**
     * Hesaplanan metriklerin birimleri (alan olmayanlar).
     *
     * @return array<string, string>
     */
    protected function extraMetricUnits(): array
    {
        return [];
    }

    protected function translate(string $key): ?string
    {
        $full = 'report.templates.'.$this->code().'.'.$key;

        if (! Lang::has($full)) {
            return null;
        }

        $value = __($full);

        return is_string($value) ? $value : null;
    }
}
