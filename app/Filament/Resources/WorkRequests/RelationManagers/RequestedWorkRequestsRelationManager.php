<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\RelationManagers;

/** Kisinin ya da departmanin actigi talepler (`requestedWorkRequests`). */
class RequestedWorkRequestsRelationManager extends WorkRequestsRelationManager
{
    protected static string $relationship = 'requestedWorkRequests';

    protected static string $titleKey = 'requested';

    protected static bool $allowsCreate = false;
}
