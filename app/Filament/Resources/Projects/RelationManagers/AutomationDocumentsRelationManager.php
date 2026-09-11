<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Document\DocumentDiscipline;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Yazilim / otomasyon adimi: SCADA, PLC ve otomasyon dokumanlari. */
class AutomationDocumentsRelationManager extends DocumentsRelationManager
{
    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCpuChip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.relation.automation_title');
    }

    protected function disciplines(): ?array
    {
        return [DocumentDiscipline::Automation];
    }

    protected function heading(): string
    {
        return __('document.relation.automation_title');
    }
}
