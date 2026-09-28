<?php

declare(strict_types=1);

namespace App\Services\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Addan benzersiz kod uretir (D-130, 28 Eylul 2026 kullanici karari: "db'de
 * alan olsun ama yeni kayitlarda alan kullanici tarafindan girilmeyecek").
 * Kural organizasyon biriminin kodundakiyle aynidir (D-42: kod addan uretilir):
 * "Satış Müdürü" -> "SATIS-MUDURU"; ayni kod varsa "-2", "-3" eklenir.
 *
 * Seed'ler kendi sabit kodunu vermeye devam edebilir; servis kod bossa bunu cagirir.
 */
final class CodeGenerator
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope  ek esitlik kosullari (pozisyonda organizasyon birimi)
     */
    public function unique(string $name, string $model, string $fallback, array $scope = [], int $maxLength = 32, string $column = 'code'): string
    {
        $base = Str::limit(strtoupper(Str::slug($name, '-')), $maxLength - 4, '');
        $base = trim($base, '-');

        if ($base === '') {
            $base = $fallback;
        }

        $code = $base;
        $suffix = 2;

        while ($model::query()->where($scope)->where($column, $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
