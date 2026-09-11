<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ProjectWizard;
use App\Services\Project\ProjectPhotoService;
use App\Services\Project\ProjectService;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Dogrudan proje olusturma sihirbazi (D-68, D-72): teklif sureci yasanmamis,
 * gecmiste yapilmis veya suren projeler. Adimlar ProjectWizard'dan gelir ve
 * duzenleme sayfasiyla aynidir; kayit ProjectService::createDirect ile
 * acilir (business case + PRJ kodu + proje tek transaction).
 */
class CreateProject extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return __('project.actions.create_direct');
    }

    public function getSubheading(): ?string
    {
        return __('project.help.create_direct_intro');
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return app(ProjectWizard::class)->baseSteps();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $coverPath = $data['cover_file'] ?? null;
        $coverName = $data['cover_original_name'] ?? null;
        $coverCaption = $data['cover_caption'] ?? null;
        unset($data['cover_file'], $data['cover_original_name'], $data['cover_caption']);

        try {
            $project = app(ProjectService::class)->createDirect($data);

            if (filled($coverPath)) {
                app(ProjectPhotoService::class)->create([
                    'project_id' => $project->getKey(),
                    'file_temp_path' => $coverPath,
                    'file_original_name' => $coverName,
                    'caption' => $coverCaption,
                    'is_cover' => true,
                ]);
            }

            DomainNotifications::success(__('project.messages.created_direct', ['code' => $project->businessCode?->formatted_code ?? '-']));

            return $project;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return null;
    }
}
