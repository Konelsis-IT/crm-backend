<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Services\AbstractService;

/**
 * BusinessDevelopmentActivityParticipant servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class BusinessDevelopmentActivityParticipantService extends AbstractService
{
    protected string $model = \App\Models\Acquisition\BusinessDevelopmentActivityParticipant::class;

    protected string $subjectType = 'bd_activity_participant';
}
