<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhoto;
use App\Models\Project\ProjectWorkstream;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Document\FileObjectService;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Saha fotografi servisi (11 SS1.10). Dosya DMS'in FileObjectService'i ile
 * saklanir (icerik hash'i ile tekillestirme); ilk fotograf veya is_cover
 * isaretli fotograf projenin kapak gorseli olur.
 */
final class ProjectPhotoService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = ProjectPhoto::class;

    /** @var list<string> */
    protected array $with = ['file', 'workstream.group'];

    protected string $orderBy = 'sort_order';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly FileObjectService $fileObjects,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  file_temp_path (+ file_original_name) veya file_object_id zorunlu
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $projectId = (int) ($data['project_id'] ?? 0);
            $this->assertSameProject($projectId, ProjectWorkstream::class, $data['workstream_id'] ?? null);

            $tempPath = $data['file_temp_path'] ?? null;
            $originalName = $data['file_original_name'] ?? null;
            unset($data['file_temp_path'], $data['file_original_name'], $data['file'], $data['files']);

            if (filled($tempPath)) {
                $file = $this->fileObjects->createFromUpload((string) $tempPath, is_string($originalName) ? $originalName : null);
                $data['file_object_id'] = $file->getKey();
            }

            if (empty($data['file_object_id'])) {
                throw GuardNotSatisfiedException::make(['reason' => 'fotograf dosyasi gerekli']);
            }

            $data['sort_order'] ??= (int) ProjectPhoto::query()->where('project_id', $projectId)->max('sort_order') + 1;
            $wantsCover = (bool) ($data['is_cover'] ?? false);
            $data['is_cover'] = false;

            /** @var ProjectPhoto $photo */
            $photo = parent::create($data);

            $projectHasCover = Project::query()->whereKey($projectId)->whereNotNull('cover_file_object_id')->exists();

            if ($wantsCover || ! $projectHasCover) {
                $this->setCover($photo);
            }

            return $photo->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var ProjectPhoto $current */
            $current = $this->show($record);
            unset($data['project_id'], $data['file_object_id'], $data['file_temp_path'], $data['file_original_name'], $data['file'], $data['files']);

            if (array_key_exists('workstream_id', $data)) {
                $this->assertSameProject((int) $current->project_id, ProjectWorkstream::class, $data['workstream_id']);
            }

            $wantsCover = (bool) ($data['is_cover'] ?? false);
            unset($data['is_cover']);

            /** @var ProjectPhoto $photo */
            $photo = parent::update($current, $data);

            if ($wantsCover && ! $photo->is_cover) {
                $this->setCover($photo);
            }

            return $photo->refresh();
        });
    }

    /** Fotografi projenin kapak gorseli yapar; onceki kapak isareti kalkar. */
    public function setCover(Model|int|string $record): ProjectPhoto
    {
        return $this->transactions->run(function () use ($record): ProjectPhoto {
            /** @var ProjectPhoto $photo */
            $photo = $this->lockForUpdate($record);

            ProjectPhoto::query()
                ->where('project_id', $photo->project_id)
                ->whereKeyNot($photo->getKey())
                ->where('is_cover', true)
                ->update(['is_cover' => false]);

            $photo->forceFill(['is_cover' => true])->save();

            Project::query()->whereKey($photo->project_id)->update(['cover_file_object_id' => $photo->file_object_id]);

            $this->recordActivity($photo, 'cover_set', ['project_id' => $photo->project_id]);

            return $photo;
        });
    }

    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var ProjectPhoto $photo */
            $photo = $this->lockForUpdate($record);

            if ($photo->is_cover) {
                Project::query()->whereKey($photo->project_id)->update(['cover_file_object_id' => null]);
            }

            return parent::delete($photo);
        });
    }
}
