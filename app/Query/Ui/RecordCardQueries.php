<?php

declare(strict_types=1);

namespace App\Query\Ui;

use App\Models\Document\Document;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kart listelerinin okuma sorgulari (D-79): arama + sayfalama. Kartin
 * gosterdigi iliskiler burada eager yuklenir; siralama tablolarin
 * varsayilaniyla aynidir (proje: yeni once, personel: ada gore, belge:
 * numara azalan).
 *
 * Arama kosullari ayri yontemlerdedir; kart gorunumunde Excel disa aktarimi
 * da ayni aramayi uygular (D-110: "ekranda gorundugu gibi").
 */
final class RecordCardQueries
{
    public function projects(?string $search, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->searchProjects(Project::query(), $search)
            ->with(['businessCode', 'customerParty', 'projectManager', 'primaryFocusWorkstream.group', 'coverPhoto.file'])
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function personnel(?string $search, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->searchPersonnel(Personnel::query(), $search)
            ->with(['orgUnit', 'competencies'])
            ->orderBy('full_name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function documents(?string $search, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->searchDocuments(Document::query(), $search)
            ->with(['documentType', 'owner', 'classification', 'currentRevision.files.fileObject', 'project'])
            ->orderByDesc('document_no')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function searchProjects(Builder $query, ?string $search): Builder
    {
        return $query->when($search !== null, function (Builder $query) use ($search): void {
            $like = self::like((string) $search);

            $query->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('site_city', 'like', $like)
                    ->orWhereHas('businessCode', fn (Builder $code): Builder => $code->where('formatted_code', 'like', $like))
                    ->orWhereHas('projectManager', fn (Builder $manager): Builder => $manager->where('full_name', 'like', $like));
            });
        });
    }

    public function searchPersonnel(Builder $query, ?string $search): Builder
    {
        return $query->when($search !== null, function (Builder $query) use ($search): void {
            $like = self::like((string) $search);

            $query->where(function (Builder $query) use ($like): void {
                $query->where('full_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('job_title', 'like', $like)
                    ->orWhereHas('orgUnit', fn (Builder $unit): Builder => $unit->where('name', 'like', $like));
            });
        });
    }

    public function searchDocuments(Builder $query, ?string $search): Builder
    {
        return $query->when($search !== null, function (Builder $query) use ($search): void {
            $like = self::like((string) $search);

            $query->where(function (Builder $query) use ($like): void {
                $query->where('document_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('documentType', fn (Builder $type): Builder => $type->where('name', 'like', $like))
                    ->orWhereHas('owner', fn (Builder $owner): Builder => $owner->where('full_name', 'like', $like));
            });
        });
    }

    /** LIKE deseni: joker karakterler kacirilir, iki yana % eklenir. */
    private static function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
