<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Services\AbstractService;

/**
 * OrganizationProfile servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class OrganizationProfileService extends AbstractService
{
    protected string $model = \App\Models\Party\OrganizationProfile::class;
}
