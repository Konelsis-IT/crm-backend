<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Odak adiminin projeden bekledigi veri turu (11 SS1.13). Her tur icin
 * sayim kurali App\Query\Project\ProjectStepReadiness icinde tanimlidir.
 */
enum ExpectationKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case SiteAddress = 'site_address';
    case PlannedDates = 'planned_dates';
    case Components = 'components';
    case Documents = 'documents';
    case Drawings = 'drawings';
    case Photos = 'photos';
    case Wbs = 'wbs';
    case Milestones = 'milestones';
    case ScheduleBaseline = 'schedule_baseline';
    case SupplyItems = 'supply_items';
    case SupplyOrdered = 'supply_ordered';
    case SupplyDelivered = 'supply_delivered';
    case Cbs = 'cbs';
    case Exposures = 'exposures';
    case TeamMembers = 'team_members';
    case WorkPackages = 'work_packages';
    case Progress = 'progress';
    case SoftwareItems = 'software_items';
    case AutomationDocuments = 'automation_documents';
}
