<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Services\Audit\ActivityRecorder;
use App\Services\Authorization\PositionRoleSync;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Personnel\Position;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pozisyon katalogu servisi.
 *
 * create ve update override edilmistir: kod, birim icinde benzersiz
 * olmalidir ve duzenlemede degistirilemez.
 */
final class PositionService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['orgUnit'];

    protected string $orderBy = 'title';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly PositionRoleSync $roles,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $orgUnitId = (int) ($data['org_unit_id'] ?? 0);

        if (Position::query()->where('org_unit_id', $orgUnitId)->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        return $this->transactions->run(function () use ($data, $code): Model {
            /** @var Position $position */
            $position = parent::create([
                ...$data,
                'code' => $code,
                'valid_from' => $data['valid_from'] ?? Carbon::now('UTC')->toDateString(),
            ]);

            // Her pozisyonun kendi rolu (D-81).
            $this->roles->ensureRoleFor($position);

            return $position;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // Kod duzenlemede degistirilmez.
        unset($data['code']);

        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var Position $position */
            $position = parent::update($record, $data);

            // Baslik degistiyse rol adi da degisir (D-81).
            $this->roles->ensureRoleFor($position);

            return $position;
        });
    }
}
