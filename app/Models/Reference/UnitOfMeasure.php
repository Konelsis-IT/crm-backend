<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\UomDimension;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\MirrorsTurkishFields;
use App\Policies\UnitOfMeasurePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sabit birim (B01; D-194 ile Ayarlar > Birimler). Satin alma kalemleri,
 * kesif / BOQ ve proje bilesenleri bu listeden secer. Kod simgeden uretilir
 * ve degismez (D-130); Ingilizce ad arayuzde yoktur, Turkce addan doldurulur
 * (D-131). Silinmez, pasife alinir: pasif birim secim listelerinden kalkar,
 * var olan kayitlar onu gostermeye devam eder.
 */
#[Table('units_of_measure')]
#[Fillable(['dimension', 'code', 'symbol', 'name_tr', 'name_en', 'base_unit_id', 'to_base_factor', 'status'])]
#[UsePolicy(UnitOfMeasurePolicy::class)]
class UnitOfMeasure extends Model
{
    use HasAuditColumns;
    use MirrorsTurkishFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimension' => UomDimension::class,
            'to_base_factor' => 'decimal:12',
            'status' => ActiveStatus::class,
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    public function isActive(): bool
    {
        $status = $this->getAttribute('status');

        return ($status instanceof ActiveStatus ? $status : ActiveStatus::tryFrom((string) $status)) !== ActiveStatus::Inactive;
    }

    public function dimensionEnum(): UomDimension
    {
        $dimension = $this->getAttribute('dimension');

        return $dimension instanceof UomDimension ? $dimension : (UomDimension::tryFrom((string) $dimension) ?? UomDimension::Other);
    }
}
