<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Personnel\Personnel;
use App\Services\Audit\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standart iz kolonları.
 *
 * created_at ve created_by_personnel_id her zaman doldurulur. Güncelleme
 * kolonları tracksUpdateAudit() false dönmediği sürece korunur. Kaydı sistem
 * oluşturduysa personel alanı NULL kalır ve arayüzde "Sistem" gösterilir.
 */
trait HasAuditColumns
{
    public function initializeHasAuditColumns(): void
    {
        $this->mergeCasts([
            'row_version' => 'integer',
            'archived_at' => 'datetime',
        ]);
    }

    public static function bootHasAuditColumns(): void
    {
        static::creating(function (Model $model): void {
            $personnelId = app(ActorContext::class)->personnelId();

            if ($model->getAttribute('created_by_personnel_id') === null) {
                $model->setAttribute('created_by_personnel_id', $personnelId);
            }

            if ($model->tracksUpdateAudit()) {
                if ($model->getAttribute('updated_by_personnel_id') === null) {
                    $model->setAttribute('updated_by_personnel_id', $personnelId);
                }

                if ($model->getAttribute('row_version') === null) {
                    $model->setAttribute('row_version', 1);
                }
            }
        });

        static::updating(function (Model $model): void {
            if (! $model->tracksUpdateAudit()) {
                return;
            }

            $model->setAttribute('updated_by_personnel_id', app(ActorContext::class)->personnelId());

            if (! $model->isDirty('row_version')) {
                $model->setAttribute('row_version', ((int) $model->getOriginal('row_version')) + 1);
            }
        });
    }

    /** Tablo güncelleme iz kolonlarını taşıyor mu? */
    public function tracksUpdateAudit(): bool
    {
        return true;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'created_by_personnel_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'updated_by_personnel_id');
    }
}
