<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Activity\PersonnelActivity;
use App\Services\Support\TransactionRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Personel hareketlerini yazar. Kayitlar yalniz eklenir; degistirilmez.
 */
final class ActivityRecorder
{
    public function __construct(
        private readonly ActorContext $actor,
        private readonly TransactionRunner $transactions,
        private readonly ?Request $request = null,
    ) {}

    public function record(ActivityInput $input): PersonnelActivity
    {
        $this->transactions->assertInTransaction('ActivityRecorder::record');

        return PersonnelActivity::query()->create([
            'personnel_id' => $this->actor->personnelId(),
            'subject_type' => $input->subjectType,
            'subject_id' => (string) $input->subjectId,
            'action_code' => $input->actionCode,
            'channel' => $input->channel,
            'changes' => $this->hideSensitive($input->changes),
            'ip_address' => $this->request?->ip(),
            'occurred_at' => Carbon::now('UTC'),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    private function hideSensitive(?array $changes): ?array
    {
        if ($changes === null) {
            return null;
        }

        $hidden = array_map('strtolower', (array) config('konelsis.activity.hidden_keys', []));

        $walk = function (array $data) use (&$walk, $hidden): array {
            foreach ($data as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), $hidden, true)) {
                    $data[$key] = '***';

                    continue;
                }

                if (is_array($value)) {
                    $data[$key] = $walk($value);
                }
            }

            return $data;
        };

        return $walk($changes);
    }
}
