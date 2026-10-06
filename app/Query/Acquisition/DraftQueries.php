<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Taslak kayit sorgulari (B43, D-155): ihale, potansiyel is ve teklif
 * listelerindeki "Taslaklar" sekmesi.
 */
final class DraftQueries
{
    public function onlyDrafts(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_draft'), true);
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function draftCount(string $model): int
    {
        return $model::query()->where('is_draft', true)->count();
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function total(string $model): int
    {
        return $model::query()->count();
    }
}
