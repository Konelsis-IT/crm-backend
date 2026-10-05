<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Yayin kaydi (B42, D-151): canlida yayinlanan surum. En son satir gecerli
 * yayin surumudur; ondan buyuk surumdeki ozellikler gorunmez. Satirlar yalniz
 * eklenir (`konelsis:release`), degistirilmez ve silinmez.
 */
#[Table('feature_releases')]
#[Fillable(['version', 'published_at', 'note'])]
class FeatureRelease extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }
}
