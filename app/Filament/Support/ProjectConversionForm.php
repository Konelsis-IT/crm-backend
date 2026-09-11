<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Query\Personnel\PersonnelQueries;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/**
 * "Projeye donustur" modalinin form bilesenleri; Teklif ve Is Dosyasi
 * gorunum sayfalarinda ortak (ProjectConversionService girdisi).
 */
final class ProjectConversionForm
{
    /**
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function components(Proposal $proposal): array
    {
        $case = $proposal->businessCase;

        return [
            Section::make(__('project.sections.identity'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('proposal_version_id')
                        ->label(__('project.fields.proposal_version'))
                        ->options(fn (): array => $proposal->versions()
                            ->orderByDesc('version_no')
                            ->get()
                            ->mapWithKeys(fn (ProposalVersion $version): array => [
                                (int) $version->getKey() => 'v'.$version->version_no.' · '.$version->status->getLabel(),
                            ])
                            ->all())
                        ->default(fn (): ?int => $proposal->versions()->orderByDesc('version_no')->value('id'))
                        ->required()
                        ->native(false),
                    Toggle::make('approve_draft')
                        ->label(__('project.fields.approve_draft'))
                        ->helperText(__('project.help.approve_draft'))
                        ->default(true)
                        ->inline(false),
                    TextInput::make('name')
                        ->label(__('project.fields.name'))
                        ->default((string) ($case?->title ?? $proposal->title))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('project_manager_employee_id')
                        ->label(__('project.fields.project_manager'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->default(fn (): ?int => $case?->proposal_owner_employee_id ?? $case?->owner_employee_id)
                        ->searchable()
                        ->required()
                        ->native(false),
                    DatePicker::make('planned_start_on')
                        ->label(__('project.fields.planned_start_on'))
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('planned_finish_on')
                        ->label(__('project.fields.planned_finish_on'))
                        ->displayFormat('d.m.Y')
                        ->afterOrEqual('planned_start_on'),
                ])),
            Section::make(__('project.sections.site'))
                ->columns(FieldGrid::COLUMNS)
                ->collapsed()
                ->components(FieldGrid::fields([
                    TextInput::make('site_address_line1')
                        ->label(__('project.fields.site_address_line1'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    ...TurkiyeAddressFields::make('site_city', 'site_district', __('project.fields.site_city'), __('project.fields.site_district')),
                ])),
            Textarea::make('comment')
                ->label(__('project.fields.comment'))
                ->maxLength(500),
        ];
    }
}
