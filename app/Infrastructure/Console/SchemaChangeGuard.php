<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;

/**
 * Refuses Artisan commands that change or seed the schema unless the DBA has
 * explicitly allowed them for the current shell (docs/planning/02 M01 exit
 * criterion "the application cannot change the schema on its own").
 */
final class SchemaChangeGuard
{
    /** @var list<string> */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    /** @var list<string> */
    private const SCHEMA_COMMANDS = [
        'migrate',
        'migrate:install',
        'migrate:status',
        'db:seed',
        'schema:dump',
    ];

    public function __construct(private readonly Application $app) {}

    public function handle(CommandStarting $event): void
    {
        $command = (string) $event->command;

        if ($command === '' || ! $this->isSchemaCommand($command)) {
            return;
        }

        $config = (array) $this->app['config']->get('konelsis.schema_guard', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return;
        }

        if (! (bool) ($config['changes_allowed'] ?? false)) {
            throw new SchemaChangeBlockedException(
                "Konelsis schema guard: [{$command}] is refused. Schema and seed commands are applied only by the authorized DBA/DevOps process with KONELSIS_SCHEMA_CHANGES_ALLOWED=true (docs/planning/16)."
            );
        }

        if ($this->isDestructive($command)) {
            if ($this->app->isProduction()) {
                throw new SchemaChangeBlockedException(
                    "Konelsis schema guard: destructive command [{$command}] is never allowed in production."
                );
            }

            if (! (bool) ($config['destructive_allowed'] ?? false)) {
                throw new SchemaChangeBlockedException(
                    "Konelsis schema guard: destructive command [{$command}] requires KONELSIS_DESTRUCTIVE_SCHEMA_ALLOWED=true in addition to the schema change flag."
                );
            }
        }
    }

    private function isSchemaCommand(string $command): bool
    {
        return in_array($command, self::SCHEMA_COMMANDS, true)
            || in_array($command, self::DESTRUCTIVE_COMMANDS, true)
            || str_starts_with($command, 'migrate:');
    }

    private function isDestructive(string $command): bool
    {
        return in_array($command, self::DESTRUCTIVE_COMMANDS, true);
    }
}
