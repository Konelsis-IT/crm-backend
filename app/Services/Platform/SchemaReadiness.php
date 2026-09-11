<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Tells whether a migration batch (docs/planning/16 §4) has been confirmed as
 * applied by the DBA for the current environment. Feature surfaces gate on
 * this instead of probing the schema at runtime.
 *
 * KONELSIS_APPLIED_SCHEMA_BATCH is a comma-separated SET of applied batch
 * codes (e.g. "B00,B01,B02,B08"), not a single "highest" batch: batches are
 * not always authored/applied in strict alphabetical order (e.g. B25 was
 * built and applied before B03), so membership is checked directly rather
 * than by ordinal position in a fixed sequence.
 */
final class SchemaReadiness
{
    /**
     * @return list<string>
     */
    public static function appliedBatches(): array
    {
        $raw = (string) config('konelsis.applied_schema_batch', '');

        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $batch): string => strtoupper(trim($batch)),
            explode(',', $raw),
        ), static fn (string $batch): bool => $batch !== ''));
    }

    public static function hasBatch(string $batch): bool
    {
        return in_array(strtoupper($batch), self::appliedBatches(), true);
    }
}
