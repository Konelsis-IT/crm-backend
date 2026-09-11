<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Services\AbstractService;

/**
 * PersonProfile servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class PersonProfileService extends AbstractService
{
    protected string $model = \App\Models\Party\PersonProfile::class;
}
