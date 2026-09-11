#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konelsis safe verification (docs/planning/01 §19, 16 §8, decision D-39)
|--------------------------------------------------------------------------
|
| Runs only checks that can neither execute application tests nor touch a
| database:
|   1. PHP syntax lint of project sources
|   2. composer.json script scan for prohibited commands
|   3. Filament layer boundary scan (raw SQL / DB facade / query chains)
|   4. Runtime schema-change scan outside migrations
|   5. Migration destructive-statement alarm
|
| Usage: php tools/safe-verify.php   (exit code 1 on failure)
*/

$root = dirname(__DIR__);
$failures = [];
$warnings = [];

/**
 * @return Generator<string>
 */
function phpFiles(string $directory): Generator
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            yield $file->getPathname();
        }
    }
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

// 1. Syntax lint -----------------------------------------------------------
$lintDirectories = ['app', 'bootstrap', 'config', 'database', 'routes', 'tools', 'lang'];
$linted = 0;

foreach ($lintDirectories as $directory) {
    foreach (phpFiles($root.DIRECTORY_SEPARATOR.$directory) as $file) {
        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $exitCode);
        $linted++;

        if ($exitCode !== 0) {
            $failures[] = sprintf('Syntax error in %s: %s', relative($root, $file), trim(implode(' ', $output)));
        }
    }
}

// 2. Composer script scan ---------------------------------------------------
$composerJson = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$prohibitedTokens = ['artisan migrate', 'db:wipe', 'artisan test', 'vendor/bin/pint', 'vendor\\bin\\pint', 'phpunit', 'pest', 'artisan db:seed'];

foreach ((array) ($composerJson['scripts'] ?? []) as $scriptName => $commands) {
    foreach ((array) $commands as $command) {
        foreach ($prohibitedTokens as $token) {
            if (is_string($command) && stripos($command, $token) !== false) {
                $failures[] = sprintf('composer.json script [%s] invokes prohibited command token [%s].', $scriptName, $token);
            }
        }
    }
}

// 3. Filament layer boundary ---------------------------------------------------
$filamentForbidden = [
    '/\bDB::/' => 'DB facade',
    '/->(whereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|fromRaw)\(/' => 'raw SQL',
    '/::query\(\)\s*->/' => 'explicit Eloquent query chain',
    '/->newQuery\(\)/' => 'explicit query builder',
    '/Illuminate\\\\Support\\\\Facades\\\\DB\b/' => 'DB facade import',
    '/Illuminate\\\\Support\\\\Facades\\\\Schema\b/' => 'Schema facade import',
];
$filamentWriteWarnings = ['/->save\(\)/' => 'model save()', '/::create\(\[/' => 'mass create()', '/->update\(\[/' => 'direct update()', '/->delete\(\)/' => 'direct delete()'];

foreach (phpFiles($root.'/app/Filament') as $file) {
    $source = (string) file_get_contents($file);

    foreach ($filamentForbidden as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $failures[] = sprintf('Filament boundary: %s uses %s; move it to a Query Service or use case.', relative($root, $file), $label);
        }
    }

    foreach ($filamentWriteWarnings as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $warnings[] = sprintf('Filament boundary: %s performs %s; writes belong to application use cases.', relative($root, $file), $label);
        }
    }
}

// 4. Runtime schema change scan ---------------------------------------------
$schemaPatterns = [
    '/Artisan::call\(\s*[\'"](migrate|db:wipe|db:seed)/' => 'programmatic migration/seed call',
    '/\bSchema::(create|table|drop|dropIfExists|rename)\(/' => 'runtime schema change',
];

foreach (phpFiles($root.'/app') as $file) {
    if (str_contains(str_replace('\\', '/', $file), '/app/Infrastructure/Database/Migrations/')) {
        continue;
    }

    $source = (string) file_get_contents($file);

    foreach ($schemaPatterns as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $failures[] = sprintf('Schema guard: %s contains a %s.', relative($root, $file), $label);
        }
    }
}

// 5. Migration destructive-statement alarm -------------------------------------
foreach (phpFiles($root.'/database/migrations') as $file) {
    $source = (string) file_get_contents($file);
    $relativePath = relative($root, $file);

    if (preg_match('/\btruncate\b/i', $source) === 1) {
        $failures[] = sprintf('Migration %s contains TRUNCATE.', $relativePath);
    }

    if (preg_match('/->delete\(|DELETE\s+FROM/i', $source) === 1) {
        $failures[] = sprintf('Migration %s deletes data; data corrections belong to the DBA script package.', $relativePath);
    }

    if (preg_match('/dropIfExists\(|->drop\(|dropColumn\(|dropForeign\(/', $source) === 1
        && str_contains($source, 'assertDestructiveAllowed') === false) {
        $failures[] = sprintf('Migration %s has destructive steps without assertDestructiveAllowed().', $relativePath);
    }
}

// Report ------------------------------------------------------------------------
fwrite(STDOUT, sprintf("Konelsis safe verification: %d PHP files linted.%s", $linted, PHP_EOL));

foreach ($warnings as $warning) {
    fwrite(STDOUT, 'WARN  '.$warning.PHP_EOL);
}

foreach ($failures as $failure) {
    fwrite(STDERR, 'FAIL  '.$failure.PHP_EOL);
}

if ($failures !== []) {
    fwrite(STDERR, sprintf('%d failure(s).%s', count($failures), PHP_EOL));
    exit(1);
}

fwrite(STDOUT, 'OK    No policy violations found. (Pint, test suites and migrations are intentionally not part of this check.)'.PHP_EOL);
exit(0);
