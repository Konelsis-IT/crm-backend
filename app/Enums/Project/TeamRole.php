<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Proje ekibindeki gorev (11 SS1.12). */
enum TeamRole: string implements HasLabel
{
    use HasTranslatedLabel;

    case ProjectManager = 'project_manager';
    case SiteManager = 'site_manager';
    case Engineer = 'engineer';
    case Technician = 'technician';
    case ProcurementOfficer = 'procurement_officer';
    case Accountant = 'accountant';
    case LogisticsOfficer = 'logistics_officer';
    case SoftwareEngineer = 'software_engineer';
    case QaQc = 'qa_qc';
    case HseOfficer = 'hse_officer';
    case Other = 'other';
}
