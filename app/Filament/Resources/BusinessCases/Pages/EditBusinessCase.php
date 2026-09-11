<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Models\Acquisition\BusinessCase;
use App\Services\Acquisition\BusinessCaseService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Is dosyasi duzenleme sihirbazi (D-72): olusturma sihirbaziyla ayni uc
 * adim — 1 alanlar ("Kaydet"), 2 teklifler tablosu, 3 proje / donusum.
 * Zincirde gelinen adimdan (veya ?step= ile istenen adimdan) acilir.
 */
class EditBusinessCase extends EditRecord
{
    use HasWizard;

    protected static string $resource = BusinessCaseResource::class;

    #[Url]
    public ?string $step = null;

    public function getSubheading(): ?string
    {
        return __('business_case.help.edit_intro');
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        $wizard = app(BusinessCaseWizard::class);
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return [
            $wizard->caseStep(),
            $wizard->proposalTableStep($case, static::class),
            $wizard->projectStep($case),
        ];
    }

    public function getStartStep(): int
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return app(BusinessCaseWizard::class)->startStep($case, $this->step);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_now')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(BusinessCaseService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    /** Servis taze ornek uzerinden yazdigi icin kayit ve form yenilenir (row_version). */
    protected function afterSave(): void
    {
        $this->record = $this->getRecord()->fresh() ?? $this->getRecord();
        $this->fillForm();
    }
}
