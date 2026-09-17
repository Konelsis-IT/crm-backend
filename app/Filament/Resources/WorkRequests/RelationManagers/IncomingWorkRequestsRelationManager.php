<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\RelationManagers;

/** Kisiye ya da departmana gelen talepler (`incomingWorkRequests`). */
class IncomingWorkRequestsRelationManager extends WorkRequestsRelationManager
{
    protected static string $relationship = 'incomingWorkRequests';

    protected static string $titleKey = 'incoming';
}
