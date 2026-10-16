<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Model;

/**
 * Satin alma politikalarinin ortak hayir cevaplari (D-189): kalici silme,
 * geri yukleme, cogaltma ve siralama yok; is kayitlari arsive alinir (D-156).
 */
trait ProcurementPolicyDefaults
{
    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Model $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Model $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
