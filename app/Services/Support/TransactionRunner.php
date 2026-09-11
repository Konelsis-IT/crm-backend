<?php

declare(strict_types=1);

namespace App\Services\Support;

use Illuminate\Database\ConnectionInterface;
use LogicException;
use Throwable;

/**
 * Transaction boundary helper for application services.
 *
 * Laravel's transaction() already retries deadlocks up to $attempts times,
 * which implements the bounded deadlock retry standard of docs/planning/03 §5.2.
 */
final class TransactionRunner
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     * @throws Throwable
     */
    public function run(callable $callback, int $attempts = 3): mixed
    {
        return $this->connection->transaction($callback, $attempts);
    }

    public function isInTransaction(): bool
    {
        return $this->connection->transactionLevel() > 0;
    }

    public function assertInTransaction(string $operation): void
    {
        if (! $this->isInTransaction()) {
            throw new LogicException("[{$operation}] must run inside the owning use case transaction.");
        }
    }
}
