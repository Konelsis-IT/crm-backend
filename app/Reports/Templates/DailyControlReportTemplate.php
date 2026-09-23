<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportSubjectKind;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use Illuminate\Support\Collection;

/**
 * Gunluk kontrol raporu (B36, D-115; gunluge cevrildi D-116, 23 Eylul 2026:
 * "Haftalik kontrol matrisi gunluk doldurulabilir olmali"): IK kontrol
 * matrisinde her personeli bolumunun kriterlerine gore gun gun isaretler;
 * Kaydet kisi basina (bolum + gun) bir rapor yazar. Rapor formundan
 * yazilmaz, yalniz matris sayfasi uretir.
 *
 * Haftalik gorunum bu gunluk raporlarin toplamidir; ayrica bir haftalik
 * rapor tutulmaz. Gizlidir: yalniz ust yonetim gorur, kisinin kendisi gormez.
 *
 * payload: section (bolum kodu), section_label, marks { kriter: ok | bad },
 * results { kriter adi: sonuc metni } (gorunum), note (aciklama).
 */
final class DailyControlReportTemplate extends ReportTemplate
{
    public const CODE = 'daily_control';

    public function code(): string
    {
        return self::CODE;
    }

    public function kind(): ReportKind
    {
        return ReportKind::Personnel;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Evaluation;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::Personnel;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Day;
    }

    public function isConfidential(): bool
    {
        return true;
    }

    public function isManualEntry(): bool
    {
        return false;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::text('section_label'),
            ReportField::keyValue('results'),
            ReportField::longText('note', 2)->summary(),
        ];
    }

    /**
     * Uygun / isaretli kriter sayisi ve uygunluk yuzdesi.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $marks = is_array($payload['marks'] ?? null) ? $payload['marks'] : [];
        $checked = count(array_filter($marks, static fn ($mark): bool => in_array($mark, ['ok', 'bad'], true)));
        $ok = count(array_filter($marks, static fn ($mark): bool => $mark === 'ok'));

        return [
            'control_ok_count' => (float) $ok,
            'control_checked_count' => (float) $checked,
            'control_compliance_pct' => $checked > 0 ? round($ok * 100 / $checked, 2) : 0.0,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function extraMetricUnits(): array
    {
        return ['control_ok_count' => 'adet', 'control_checked_count' => 'adet', 'control_compliance_pct' => '%'];
    }
}
