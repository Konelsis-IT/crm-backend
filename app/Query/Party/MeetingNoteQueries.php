<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;

/** Gorusme notu okuma sorgulari (B44, D-156: arsiv suzgeci). */
final class MeetingNoteQueries
{
    /**
     * Arsiv suzgeci: active (varsayilan) / archived / all. Taraf arsivinin
     * (PartyQueries::archiveScope) aynisi; grup uygulanmadiysa suzgec yoktur.
     */
    public function archiveScope(Builder $query, string $mode): Builder
    {
        if (! SchemaReadiness::hasBatch('B44')) {
            return $query;
        }

        return match ($mode) {
            'archived' => $query->whereNotNull($query->qualifyColumn('archived_at')),
            'all' => $query,
            default => $query->whereNull($query->qualifyColumn('archived_at')),
        };
    }
}
