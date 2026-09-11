<?php

declare(strict_types=1);

namespace App\Query\Activity;

use App\Models\Activity\PersonnelActivity;
use App\Support\ActivityLabels;

/**
 * Personel hareketleri ekranindaki filtre secenekleri.
 */
final class ActivityFilterOptions
{
    /**
     * Kayit turleri; anahtar teknik deger, deger Turkce etiket.
     *
     * @return array<string, string>
     */
    public function subjectTypes(): array
    {
        $types = PersonnelActivity::query()
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->all();

        $options = [];

        foreach ($types as $type) {
            $options[$type] = __("activity.subjects.{$type}");
        }

        return $options;
    }

    /**
     * Yapilan islemler; anahtar teknik kod, deger Turkce cumle.
     *
     * @return array<string, string>
     */
    public function actionCodes(): array
    {
        $codes = PersonnelActivity::query()
            ->select('action_code')
            ->distinct()
            ->orderBy('action_code')
            ->pluck('action_code')
            ->all();

        $options = [];

        foreach ($codes as $code) {
            $options[$code] = ActivityLabels::action($code);
        }

        asort($options);

        return $options;
    }
}
