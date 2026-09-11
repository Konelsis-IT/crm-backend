<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Model-level guard for A-class (append-only) tables (docs/planning/13 §8.1).
 * The database role restriction is the primary control; this trait stops
 * accidental updates and deletes through Eloquent.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (Model $model): void {
            throw new LogicException(sprintf('%s is append-only; rows cannot be updated.', $model::class));
        });

        static::deleting(function (Model $model): void {
            throw new LogicException(sprintf('%s is append-only; rows cannot be deleted.', $model::class));
        });
    }
}
