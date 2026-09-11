<?php

declare(strict_types=1);

namespace App\Query\Ui;

use App\Models\Document\Document;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kart tasarimi deneme sayfasinin okuma sorgulari (salt okunur, gercek veri).
 */
final class CardGalleryQueries
{
    /**
     * @return Collection<int, Project>
     */
    public function projects(int $limit = 8): Collection
    {
        return Project::query()
            ->with(['businessCode', 'customerParty', 'projectManager', 'primaryFocusWorkstream.group', 'coverPhoto.file'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** Tablo tabanli kart denemesi icin proje sorgusu (arama/siralama/sayfalama tabloda). */
    public function projectsForTable(): Builder
    {
        return Project::query()
            ->with(['businessCode', 'customerParty', 'projectManager', 'primaryFocusWorkstream.group', 'coverPhoto.file']);
    }

    /**
     * @return Collection<int, Personnel>
     */
    public function personnel(int $limit = 8): Collection
    {
        return Personnel::query()
            ->with(['orgUnit', 'competencies'])
            ->orderBy('full_name')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Document>
     */
    public function documents(int $limit = 8): Collection
    {
        return Document::query()
            ->with(['documentType', 'owner', 'classification', 'currentRevision.files.fileObject', 'project'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
