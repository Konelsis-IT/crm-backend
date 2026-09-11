<?php

declare(strict_types=1);

namespace App\Enums\Activity;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Kontrollu genel referansin nerede kullanilabilecegi.
 */
enum ReferenceUsageContext: string implements HasLabel
{
    use HasTranslatedLabel;

    case DocumentLink = 'document_link';
    case MessageLink = 'message_link';
    case EmailLink = 'email_link';
    case Activity = 'activity';
    case WorkflowSubject = 'workflow_subject';
    case ApprovalSubject = 'approval_subject';
    case NotificationSubject = 'notification_subject';
    case TaskContext = 'task_context';
    case GeneratedOutputSource = 'generated_output_source';
    case ReportScheduleTarget = 'report_schedule_target';
    case InventorySource = 'inventory_source';
    case ProjectChangeSource = 'project_change_source';
}
