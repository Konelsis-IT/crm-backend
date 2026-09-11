<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\ProjectWorkstream;
use App\Policies\OperationGroupDefinitionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('operation_group_definitions')]
#[Fillable(['code', 'name_tr', 'name_en', 'default_sort_order', 'status'])]
#[UsePolicy(OperationGroupDefinitionPolicy::class)]
class OperationGroupDefinition extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_sort_order' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }

    public function workstreams(): HasMany
    {
        return $this->hasMany(ProjectWorkstream::class, 'group_definition_id');
    }

    /** Bu odak adiminin projeden bekledigi veriler (11 SS1.13). */
    public function expectations(): HasMany
    {
        return $this->hasMany(FocusExpectation::class, 'group_definition_id')->orderBy('sort_order');
    }

    /** Arayuz diline gore ad. */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->name_en) ? (string) $this->name_en : (string) $this->name_tr;
    }
}
