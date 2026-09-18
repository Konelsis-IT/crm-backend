<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Position;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sosyal medyadan sorumlu gorev (B31, D-106). Sorumlu personel, bu
 * pozisyonlardan birini su an tutan AKTIF personeldir. Pozisyon basina tek
 * satir vardir; secimden cikan satir kaldirilmaz, `status` ile pasife alinir
 * ve sorumluluk sorgulari yalniz aktif satirlara bakar.
 *
 * Bu modelin tablosu B31 grubunun son tablosu ve SchemaReadiness imzasidir.
 */
#[Table('social_responsible_positions')]
#[Fillable(['position_id', 'status'])]
class SocialResponsiblePosition extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }
}
