<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Project\Project;
use App\Support\Projects\ProjectNames;

/** Projeler tablosu (ProjectResource) sutunlari. */
class ProjectExporter extends KonelsisExporter
{
    protected static ?string $model = Project::class;

    public static function fileLabel(): string
    {
        return __('project.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('businessCode.formatted_code', __('project.fields.business_code')),
            // D-174: kisa ad ve lisans adi.
            ...(ProjectNames::shortNameEnabled() ? [self::text('short_name', __('project.fields.short_name'))] : []),
            self::text('name', ProjectNames::nameLabel()),
            self::text('customerParty.display_name', __('project.fields.customer_party')),
            self::text('projectManager.full_name', __('project.fields.project_manager')),
            self::text('status', __('project.fields.status')),
            self::text('primaryFocusWorkstream.group.name_tr', __('project.fields.current_focus')),
            self::text('origin', __('project.fields.origin')),
            self::text('site_city', __('project.fields.site_city')),
            self::text('current_macro_gate_code', __('project.fields.current_macro_gate_code')),
            self::date('planned_finish_on', __('project.fields.planned_finish_on')),
        ];
    }
}
