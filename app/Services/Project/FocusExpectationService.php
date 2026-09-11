<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\DuplicateRecordException;
use App\Models\Project\FocusExpectation;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Odak beklentisi katalogu servisi (11 SS1.13): grup icinde kod tekil. */
final class FocusExpectationService extends AbstractService
{
    protected string $model = FocusExpectation::class;

    /** @var list<string> */
    protected array $with = ['group'];

    protected string $orderBy = 'sort_order';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $data['code'] = Str::upper(Str::snake(trim((string) ($data['code'] ?? ''))));
        $this->assertUniqueCode((int) ($data['group_definition_id'] ?? 0), $data['code'], null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var FocusExpectation $current */
        $current = $this->show($record);
        unset($data['group_definition_id']);

        if (array_key_exists('code', $data)) {
            $data['code'] = Str::upper(Str::snake(trim((string) $data['code'])));
            $this->assertUniqueCode((int) $current->group_definition_id, $data['code'], (int) $current->getKey());
        }

        return parent::update($current, $data);
    }

    private function assertUniqueCode(int $groupId, string $code, ?int $ignoreId): void
    {
        $exists = FocusExpectation::query()
            ->where('group_definition_id', $groupId)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw DuplicateRecordException::make();
        }
    }
}
