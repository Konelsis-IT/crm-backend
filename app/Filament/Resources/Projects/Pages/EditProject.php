<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\Projects\Pages\Concerns\OpensChecklistTargets;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ProjectWizard;
use App\Models\Project\Project;
use App\Services\Project\ProjectPhotoService;
use App\Services\Project\ProjectService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Proje duzenleme sihirbazi (D-72): olusturma sihirbazinin dort temel adimi
 * + projenin departman adimlari (beklenti listesi ve tablolar). Sihirbaz
 * projenin mevcut odagindan (veya ?step= ile istenen adimdan) acilir; her
 * adim serbestce secilebilir. Ust alanlar "Kaydet" ile, departman tablolari
 * aninda kaydedilir.
 */
class EditProject extends EditRecord
{
    use HasSaveableWizard;
    use OpensChecklistTargets;

    protected static string $resource = ProjectResource::class;

    #[Url]
    public ?string $step = null;

    public function getSubheading(): ?string
    {
        return __('project.help.edit_intro');
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        $wizard = app(ProjectWizard::class);
        /** @var Project $project */
        $project = $this->getRecord();

        return [
            ...$wizard->baseSteps($project, static::class),
            ...$wizard->departmentSteps($project, static::class, eagerKeys: $this->eagerRelations),
        ];
    }

    public function getStartStep(): int
    {
        /** @var Project $project */
        $project = $this->getRecord();

        return app(ProjectWizard::class)->startStep($project, $this->step, withBaseSteps: true);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    /** Temel adimlar alt satirda "Kaydet" tasir; departman adimlarinin tablolari aninda kaydeder. */
    protected function getStepSaveMethods(): array
    {
        return [
            ProjectWizard::STEP_IDENTITY => 'save',
            ProjectWizard::STEP_SITE => 'save',
            ProjectWizard::STEP_PLAN => 'save',
            ProjectWizard::STEP_PHOTO => 'save',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_now')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),
            ViewAction::make()
                ->label(__('project.actions.open_workspace'))
                ->icon(Heroicon::OutlinedRocketLaunch),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $coverPath = $data['cover_file'] ?? null;
        $coverName = $data['cover_original_name'] ?? null;
        $coverCaption = $data['cover_caption'] ?? null;
        unset($data['cover_file'], $data['cover_original_name'], $data['cover_caption']);

        try {
            $project = app(ProjectService::class)->update($record, $data);

            if (filled($coverPath)) {
                app(ProjectPhotoService::class)->create([
                    'project_id' => $project->getKey(),
                    'file_temp_path' => $coverPath,
                    'file_original_name' => $coverName,
                    'caption' => $coverCaption,
                    'is_cover' => true,
                ]);
            }

            return $project;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    /**
     * Servis kaydi taze bir ornek uzerinden guncelledigi icin sayfadaki kayit
     * ve form (row_version, kapak alani) yeniden doldurulur; boylece ayni
     * sayfada ikinci kayit "bayat kayit" hatasina dusmez.
     */
    protected function afterSave(): void
    {
        $this->record = $this->getRecord()->fresh() ?? $this->getRecord();
        $this->fillForm();
    }
}
