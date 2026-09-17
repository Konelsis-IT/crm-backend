<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\Project\FocusDirection;
use App\Enums\Project\ProjectStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Projects\Pages\Concerns\OpensChecklistTargets;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkRequests\RelationManagers\RelatedWorkRequestsRelationManager;
use App\Filament\Resources\Reports\RelationManagers\SubjectReportsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ChangesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\CustomerLicensesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\DecisionsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\DepartmentHandoffsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\FocusHistoriesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Projects\RelationManagers\RisksRelationManager;
use App\Filament\Resources\Projects\RelationManagers\StageInstancesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WorkstreamsRelationManager;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ProjectWizard;
use App\Filament\Support\ProjectWorkspace;
use App\Models\Project\Project;
use App\Query\Project\ProjectStepReadiness;
use App\Services\Project\ProjectService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Proje calisma alani (D-68, D-72): duzenleme formu degil. Proje karti
 * (baglantili alanlar, tiklanabilir adres), "su an / sirada / eksik" uyarisi,
 * adim cubugu (her adim bir kart) ve ust sekmeler: Genel bakis, her
 * departmanin kendi adim sekmesi (durum seridi + "Bu adimda beklenenler" +
 * dogrudan ekrandaki tablolar), Onay kapilari, Diger kayitlar. Adim sekmesinin
 * govdesi duzenleme sihirbazindaki departman adimiyla aynidir (ProjectWizard).
 */
class ViewProject extends ViewRecord
{
    use OpensChecklistTargets;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        /** @var Project $project */
        $project = $this->getRecord();

        return ($project->businessCode?->formatted_code ?? '').' · '.$project->name;
    }

    public function content(Schema $schema): Schema
    {
        /** @var Project $project */
        $project = $this->getRecord();
        $workspace = app(ProjectWorkspace::class);
        $wizard = app(ProjectWizard::class);
        $steps = $workspace->steps($project);
        $canUpdate = Gate::allows('update', $project);

        $tabs = [
            Tab::make(__('project.tabs.overview'))
                ->icon(Heroicon::OutlinedSquares2x2)
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->components([
                        $workspace->gatesSummary($project),
                        Section::make(__('project.sections.focus_history'))
                            ->icon(Heroicon::OutlinedArrowsRightLeft)
                            ->compact()
                            ->components([$wizard->relationManager(FocusHistoriesRelationManager::class, $project, static::class, 'overview')]),
                    ]),
                    $wizard->relationManager(PhotosRelationManager::class, $project, static::class, 'overview'),
                ]),
        ];

        foreach ($steps as $index => $step) {
            $missing = $step['mandatory_total'] - $step['mandatory_met'];

            $tabs[] = Tab::make(__('project.tabs.step', ['order' => $index + 1, 'name' => $step['group_name']]))
                ->icon($workspace->stepIcon($step['group_code']))
                ->badge($step['is_ready'] ? __('project.steps.ready_badge') : __('project.steps.missing_badge', ['count' => $missing]))
                ->badgeColor($step['is_current'] ? 'primary' : ($step['is_ready'] ? 'success' : 'warning'))
                ->schema($wizard->stepBody($step, $project, static::class, $this->eagerRelations));
        }

        $tabs[] = Tab::make(__('project.tabs.gates'))
            ->icon(Heroicon::OutlinedFlag)
            ->schema([
                $wizard->relationManager(StageInstancesRelationManager::class, $project, static::class),
                $wizard->relationManager(DepartmentHandoffsRelationManager::class, $project, static::class),
            ]);

        $tabs[] = Tab::make(__('project.tabs.records'))
            ->icon(Heroicon::OutlinedRectangleStack)
            ->schema([
                $wizard->relationManager(WorkstreamsRelationManager::class, $project, static::class),
                $wizard->relationManager(RisksRelationManager::class, $project, static::class),
                $wizard->relationManager(ChangesRelationManager::class, $project, static::class),
                $wizard->relationManager(DecisionsRelationManager::class, $project, static::class),
                $wizard->relationManager(FocusHistoriesRelationManager::class, $project, static::class, 'records'),
                $wizard->relationManager(CustomerLicensesRelationManager::class, $project, static::class),
                ...(SchemaReadiness::hasBatch('B10A') ? [$wizard->relationManager(SubjectReportsRelationManager::class, $project, static::class)] : []),
                ...(SchemaReadiness::hasBatch('B11B') ? [$wizard->relationManager(RelatedWorkRequestsRelationManager::class, $project, static::class)] : []),
            ]);

        return $schema->columns(1)->components([
            $workspace->headerCard(
                $project,
                $canUpdate ? [$this->editDetailsAction()] : [],
                $canUpdate ? $wizard->quickSiteAction($project, 'edit_site') : null,
            ),
            $workspace->stepCallout($project, $steps),
            $workspace->stepper($project, $steps),
            Tabs::make('project-workspace')
                ->id('project-workspace')
                ->persistTab()
                ->contained(false)
                ->tabs($tabs),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->url(fn (): string => $this->editUrl(app(ProjectWizard::class)->currentStepId($this->getRecord()))),
            $this->advanceFocusAction(),
            $this->changeFocusAction(),
            ActionGroup::make($this->statusActions())
                ->label(__('project.actions.change_status'))
                ->icon(Heroicon::OutlinedArrowPath),
        ];
    }

    /** Duzenleme sihirbazinin verilen adimina giden baglanti. */
    private function editUrl(?string $stepId): string
    {
        $parameters = ['record' => $this->getRecord()];

        if ($stepId !== null) {
            $parameters['step'] = $stepId;
        }

        return ProjectResource::getUrl('edit', $parameters);
    }

    /** Proje kartindaki "Bilgileri duzenle" baglantisi (Kimlik adimi). */
    private function editDetailsAction(): Action
    {
        return Action::make('edit_details')
            ->label(__('project.actions.edit_details'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->link()
            ->url(fn (): string => $this->editUrl(ProjectWizard::STEP_IDENTITY));
    }

    private function advanceFocusAction(): Action
    {
        return Action::make('advance_focus')
            ->label(__('project.actions.advance_focus'))
            ->icon(Heroicon::OutlinedForward)
            ->color('primary')
            ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                && app(ProjectStepReadiness::class)->next($this->getRecord()) !== null)
            ->modalHeading(__('project.actions.advance_focus'))
            ->modalDescription(function (): string {
                $readiness = app(ProjectStepReadiness::class);
                /** @var Project $project */
                $project = $this->getRecord();
                $next = $readiness->next($project);
                $current = $readiness->current($project);
                $missing = $current === null ? [] : $readiness->missingMandatory($project, $current);

                $text = __('project.steps.callout_next', ['step' => $next?->group?->localizedName() ?? '-']);

                if ($missing !== []) {
                    $text .= ' '.__('project.steps.callout_missing', ['count' => count($missing), 'items' => implode(', ', $missing)]);
                } else {
                    $text .= ' '.__('project.steps.callout_ready');
                }

                return $text;
            })
            ->schema([
                Toggle::make('complete_current')
                    ->label(__('project.fields.complete_current'))
                    ->helperText(__('project.help.complete_current'))
                    ->default(true),
                Toggle::make('activate_next')
                    ->label(__('project.fields.activate_next'))
                    ->default(true),
                Toggle::make('force')
                    ->label(__('project.fields.force'))
                    ->helperText(__('project.help.force'))
                    ->live(),
                Textarea::make('reason')
                    ->label(__('project.fields.reason'))
                    ->required(fn (Get $get): bool => (bool) $get('force'))
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                try {
                    $project = app(ProjectService::class)->advanceFocus(
                        $this->getRecord(),
                        $data['reason'] ?? null,
                        (bool) ($data['force'] ?? false),
                        (bool) ($data['complete_current'] ?? true),
                        (bool) ($data['activate_next'] ?? true),
                    );
                    DomainNotifications::success(__('project.messages.focus_advanced', [
                        'step' => $project->primaryFocusWorkstream?->group?->localizedName() ?? '-',
                    ]));
                    $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    private function changeFocusAction(): Action
    {
        return Action::make('change_focus')
            ->label(__('project.actions.change_focus'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => Gate::allows('update', $this->getRecord()))
            ->schema([
                Select::make('workstream_id')
                    ->label(__('project.fields.target_workstream'))
                    ->helperText(__('project.help.target_workstream'))
                    ->options(fn (): array => app(ProjectStepReadiness::class)
                        ->orderedWorkstreams($this->getRecord())
                        ->mapWithKeys(fn ($workstream, int $index): array => [
                            (int) $workstream->getKey() => ($index + 1).' · '.$workstream->group->localizedName().' ('.$workstream->status->getLabel().')',
                        ])
                        ->all())
                    ->required()
                    ->native(false),
                Toggle::make('force')
                    ->label(__('project.fields.force'))
                    ->helperText(__('project.help.force'))
                    ->live(),
                Textarea::make('reason')
                    ->label(__('project.fields.reason'))
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                try {
                    $project = app(ProjectService::class)->changeFocus(
                        $this->getRecord(),
                        (int) $data['workstream_id'],
                        FocusDirection::Forward,
                        $data['reason'] ?? null,
                        (bool) ($data['force'] ?? false),
                    );
                    DomainNotifications::success(__('project.messages.focus_changed'));
                    $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (ProjectStatus::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('project.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('project.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                    && $this->getRecord()->status->canTransitionTo($target))
                ->action(function (array $data) use ($target): void {
                    try {
                        $project = app(ProjectService::class)->changeStatus($this->getRecord(), $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('project.messages.status_changed'));
                        $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
