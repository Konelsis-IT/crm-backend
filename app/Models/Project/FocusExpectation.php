<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ExpectationKind;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\FocusExpectationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Odak adiminin (operasyon grubunun) projeden bekledigi veri (11 SS1.13). */
#[Table('focus_expectations')]
#[Fillable([
    'group_definition_id', 'code', 'name_tr', 'name_en', 'kind', 'min_count', 'is_mandatory', 'help_tr', 'help_en',
    'sort_order', 'status',
])]
#[UsePolicy(FocusExpectationPolicy::class)]
class FocusExpectation extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExpectationKind::class,
            'min_count' => 'integer',
            'is_mandatory' => 'boolean',
            'sort_order' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OperationGroupDefinition::class, 'group_definition_id');
    }

    /** Arayuz diline gore ad. */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->name_en) ? (string) $this->name_en : (string) $this->name_tr;
    }

    /** Arayuz diline gore yardim metni. */
    public function localizedHelp(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->help_en) ? $this->help_en : $this->help_tr;
    }
}
