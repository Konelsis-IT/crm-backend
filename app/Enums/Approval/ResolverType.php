<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Bir adimin onaycisinin nasil bulunacagi (12 SS2.3 resolver_type).
 */
enum ResolverType: string implements HasLabel
{
    use HasTranslatedLabel;

    case Personnel = 'personnel';
    case Position = 'position';
    case LineManager = 'line_manager';
    case OrgUnitManager = 'org_unit_manager';
    case FunctionalManager = 'functional_manager';
    case ProjectRole = 'project_role';
    case FunctionalAreaRole = 'functional_area_role';
    case Executive = 'executive';
    case RbacRole = 'rbac_role';

    /**
     * Konu kaydinda belirlenen onay mercii (D-87, B11D): talep gibi konularda
     * kullanici kaydi acarken onaylayacak kisiyi secer; adim bu kisiyi kullanir.
     */
    case DesignatedApprover = 'designated_approver';

    /** Hedef kayit (personel / pozisyon) secimi gerektirir mi? */
    public function needsTarget(): bool
    {
        return in_array($this, [self::Personnel, self::Position], true);
    }

    /** Rol kodu (proje rolu / RBAC rolu) gerektirir mi? */
    public function needsRoleCode(): bool
    {
        return in_array($this, [self::ProjectRole, self::FunctionalAreaRole, self::RbacRole], true);
    }
}
