<?php

declare(strict_types=1);

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Tek tarafli mesaj silme: mesaj yalniz bu kisiye gorunmez olur (B12A eki).
 */
#[Table('message_hides')]
#[Fillable(['message_id', 'personnel_id', 'hidden_at'])]
class MessageHide extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
        ];
    }
}
