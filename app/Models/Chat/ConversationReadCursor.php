<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Kisi basina son okunan mesaj sirasi (08 SS2.7). */
#[Table('conversation_read_cursors')]
#[Fillable(['conversation_id', 'personnel_id', 'last_read_sequence'])]
class ConversationReadCursor extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_sequence' => 'integer',
        ];
    }
}
