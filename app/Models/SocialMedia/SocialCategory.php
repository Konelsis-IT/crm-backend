<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Icerik kategorisi (B31, D-106). Ad tektir; renk sabit palet adlarindan
 * biridir. Kategori silinmez, pasife alinir.
 */
#[Table('social_categories')]
#[Fillable(['name', 'color', 'sort_order', 'status'])]
class SocialCategory extends Model
{
    use HasAuditColumns;

    /**
     * Rozet ve kategori paleti: React arayuzu ve migration kisiti ayni sekiz adi kullanir.
     *
     * @var list<string>
     */
    public const COLORS = ['red', 'amber', 'emerald', 'sky', 'violet', 'stone', 'rose', 'teal'];

    public const DEFAULT_COLOR = 'red';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function contents(): HasMany
    {
        return $this->hasMany(SocialContent::class, 'category_id');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }
}
