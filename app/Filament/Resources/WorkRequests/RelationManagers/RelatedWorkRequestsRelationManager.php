<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\RelationManagers;

/** Proje, musteri (taraf) ya da urun/bilesenle ilgili talepler (`workRequests`). */
class RelatedWorkRequestsRelationManager extends WorkRequestsRelationManager
{
    protected static string $relationship = 'workRequests';

    protected static string $titleKey = 'related';
}
