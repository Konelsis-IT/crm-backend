<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

/**
 * Panolarin ve uyarilarin dayandigi departmanlar (organizasyon birimi kodu,
 * OrganizationStructureSeeder). Kodlar addan uretilir ve duzenlemede degismez
 * (D-130); kisi adi ya da id tutulmaz.
 */
final class Departments
{
    public const BUSINESS_DEVELOPMENT = 'IS_GELISTIRME';

    public const PROPOSALS = 'TEKLIF';

    public const PROJECTS = 'PROJE';

    public const PURCHASING = 'SATIN_ALMA';
}
