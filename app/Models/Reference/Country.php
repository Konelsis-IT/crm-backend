<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ISO 3166-1 country master data; natural primary key `code`.
 */
#[Table('countries')]
#[Fillable(['code', 'iso3_code', 'name_tr', 'name_en', 'default_timezone', 'status'])]
class Country extends Model
{
    use HasAuditColumns;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? (string) $this->name_en : (string) $this->name_tr;
    }
}
