<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\DependencyStatus;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\WorkstreamStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\Project;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WorkstreamDependency;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje workstream servisi (11 SS1.5, 14 SS2.26 SM-WS).
 *
 * Workstream'ler devirde acilir; burada yalniz durum gecisleri ve alan
 * guncellemesi vardir. ready: butun hard predecessor'lar completed/waived
 * olmali; blocked: gerekce zorunlu; completed: successor'lar yeniden
 * degerlendirilir; ilk aktif workstream projeyi 'active' yapar (SM-PRJ).
 */
final class ProjectWorkstreamService extends AbstractService
{
    protected string $model = ProjectWorkstream::class;

    /** @var list<string> */
    protected array $with = ['group', 'owner'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly ProjectService $projects,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['project_id'], $data['group_definition_id'], $data['status'], $data['blocked_at']);

        return parent::update($record, $data);
    }

    public function changeStatus(Model|int|string $record, WorkstreamStatus $target, ?string $reason = null): ProjectWorkstream
    {
        return $this->transactions->run(function () use ($record, $target, $reason): ProjectWorkstream {
            /** @var ProjectWorkstream $workstream */
            $workstream = $this->lockForUpdate($record);
            $from = $workstream->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $attributes = ['status' => $target];
            $now = Carbon::now('UTC');

            switch ($target) {
                case WorkstreamStatus::Ready:
                    $this->assertPredecessorsDone($workstream);
                    break;
                case WorkstreamStatus::Active:
                    $attributes['actual_start_on'] ??= $workstream->actual_start_on ?? $now->toDateString();
                    $attributes['blocked_at'] = null;
                    $attributes['block_reason'] = null;
                    break;
                case WorkstreamStatus::Blocked:
                    if (blank($reason)) {
                        throw GuardNotSatisfiedException::make(['reason' => 'blokaj gerekcesi zorunlu']);
                    }
                    $attributes['blocked_at'] = $now;
                    $attributes['block_reason'] = $reason;
                    break;
                case WorkstreamStatus::Completed:
                    $attributes['actual_finish_on'] = $workstream->actual_finish_on ?? $now->toDateString();
                    $attributes['progress_pct'] = 100;
                    break;
                case WorkstreamStatus::Waived:
                    if (blank($reason)) {
                        throw GuardNotSatisfiedException::make(['reason' => 'muafiyet gerekcesi zorunlu (D-22)']);
                    }
                    break;
                default:
                    break;
            }

            $workstream->forceFill($attributes)->save();
            $this->recordActivity($workstream, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value], 'gerekce' => $reason]);

            if ($target === WorkstreamStatus::Active) {
                $project = Project::query()->find($workstream->project_id);
                if ($project !== null && $project->status === ProjectStatus::Opening) {
                    $this->projects->changeStatus($project, ProjectStatus::Active);
                }
            }

            if (in_array($target, [WorkstreamStatus::Completed, WorkstreamStatus::Waived], true)) {
                $this->promoteSuccessors($workstream);
            }

            return $workstream;
        });
    }

    private function assertPredecessorsDone(ProjectWorkstream $workstream): void
    {
        $blocking = WorkstreamDependency::query()
            ->where('successor_workstream_id', $workstream->getKey())
            ->where('is_hard', true)
            ->where('status', DependencyStatus::Active->value)
            ->whereHas('predecessor', fn ($query) => $query->whereNotIn('status', [WorkstreamStatus::Completed->value, WorkstreamStatus::Waived->value]))
            ->count();

        if ($blocking > 0) {
            throw GuardNotSatisfiedException::make(['reason' => "{$blocking} hard bagimlilik henuz tamamlanmadi"]);
        }
    }

    private function promoteSuccessors(ProjectWorkstream $workstream): void
    {
        $successors = WorkstreamDependency::query()
            ->where('predecessor_workstream_id', $workstream->getKey())
            ->where('status', DependencyStatus::Active->value)
            ->with('successor')
            ->get()
            ->pluck('successor')
            ->filter(fn (?ProjectWorkstream $successor): bool => $successor?->status === WorkstreamStatus::NotReady);

        foreach ($successors as $successor) {
            try {
                $this->changeStatus($successor, WorkstreamStatus::Ready);
            } catch (GuardNotSatisfiedException) {
                // Diger hard predecessor'lar bekliyor; successor not_ready kalir.
            }
        }
    }
}
