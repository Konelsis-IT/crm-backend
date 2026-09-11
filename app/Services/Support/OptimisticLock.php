<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Exceptions\StaleRecordException;
use App\Services\Audit\ActorContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Ayni kaydi iki kisi ayni anda degistirdiginde ikincisini uyarir
 * (row_version kontrolu).
 */
final class OptimisticLock
{
    public function __construct(private readonly ActorContext $actor) {}

    /**
     * Modelin degisen alanlarini surum kontroluyle kaydeder.
     *
     * @return array<string, array{onceki: mixed, yeni: mixed}> Hareket kaydi icin degisiklik ozeti.
     */
    public function save(Model $model, int $expectedVersion): array
    {
        $dirty = $model->getDirty();

        if ($dirty === []) {
            return [];
        }

        $changes = [];

        foreach (array_keys($dirty) as $attribute) {
            $changes[$attribute] = [
                'onceki' => $model->getOriginal($attribute),
                'yeni' => $model->getAttribute($attribute),
            ];
        }

        $values = $dirty + [
            'row_version' => $expectedVersion + 1,
            'updated_at' => $model->freshTimestamp(),
        ];

        if (array_key_exists('updated_by_personnel_id', $model->getAttributes()) || $model->isFillable('updated_by_personnel_id')) {
            $model->setAttribute('updated_by_personnel_id', $this->actor->personnelId());
            $values['updated_by_personnel_id'] = $model->getAttributes()['updated_by_personnel_id'];
        }

        $affected = $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->where('row_version', $expectedVersion)
            ->update($values);

        if ($affected !== 1) {
            throw StaleRecordException::make();
        }

        $model->setAttribute('row_version', $expectedVersion + 1);
        $model->setAttribute('updated_at', $values['updated_at']);
        $model->syncOriginal();

        return $changes;
    }
}
