<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\Acquisition\OfferType;
use App\Filament\Support\ActionColors;
use App\Filament\Support\ChecklistSchema;
use App\Filament\Support\DraftSupport;
use App\Models\Acquisition\BusinessCase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Potansiyel is kaydinda ve "Ileri"de kontrol listesi ozeti (B43, D-155; D-157).
 *
 * Kullanici talimati (5 Ekim 2026): "potansiyel isler kaydet butonuna
 * basildiginda kontrol edilmesi icin modal acilacak ... hangilerinin
 * tamamlanmadigi modalda gorulecek ve onlari tamamlamasi icin personele uyari
 * verilecektir"; ayni gun: "Potansiyel is kaydet - ileri butonunda ... hangilerinde
 * ne dolduruldu, kalp atis orani nedir, basari orani, teklife gecerken guncel
 * durum nedir ozeti gosterecek, hatta personeli daha fazla bilgi doldurmaya
 * tesvik edecek".
 *
 * - Kaydet (potansiyel is adimi; duzenleme sayfasinin Kaydet'i): once form
 *   dogrulanir, sonra ozet acilir: "Kaydet" / "Taslak olarak kaydet" / "Geri
 *   don, tamamla". Taslak kaydi pencere acmaz.
 * - Ileri (potansiyel is adimindan): ayni ozet; "Teklif adimina gec" bir sonraki
 *   adima gecirir (SaveableWizard::guardNextOnSteps).
 * - Pencere yalniz kontrol listesi acik ve en az bir liste secili iken acilir.
 */
trait ConfirmsChecklist
{
    /** Pencerede onaylandi; bir sonraki kaydetme pencereyi atlar. */
    public bool $checklistConfirmed = false;

    /** Bu kaydetme taslak olarak yazilir. */
    public bool $saveAsDraft = false;

    /** Kaydetme oncesi formdaki teklif tipi (GES 1.3 kurali bildirimi icin). */
    protected ?string $submittedOfferType = null;

    /** Bu istekteki kaydetme ozeti atlar (or. ihale adiminin Kaydet'i). */
    protected bool $checklistSkipOnce = false;

    /** Kaydetmeyi durdurup ozet penceresini acti mi. */
    protected function checklistBlocks(): bool
    {
        if ($this->saveAsDraft || $this->checklistConfirmed || $this->checklistSkipOnce || ! $this->checklistSummaryOnSave()) {
            $this->checklistConfirmed = false;
            $this->checklistSkipOnce = false;

            return false;
        }

        if (! ChecklistSchema::hasTemplates($this->checklistState())) {
            return false;
        }

        // Zorunlu alan eksikse pencere yerine alanin kendi hatasi gorunur.
        $this->form->validate();

        $this->mountAction('checklistSummary', ['mode' => 'save']);

        return true;
    }

    /**
     * Potansiyel is adimindaki "Ileri": ozet penceresi acilir, gecis pencereden
     * yapilir. Liste yoksa false (sihirbaz dogrudan ilerler).
     */
    public function confirmCaseStepNext(string $wizardKey): bool
    {
        if (! ChecklistSchema::hasTemplates($this->checklistState())) {
            return false;
        }

        $this->mountAction('checklistSummary', ['mode' => 'next', 'wizard' => $wizardKey]);

        return true;
    }

    public function checklistSummaryAction(): Action
    {
        return Action::make('checklistSummary')
            ->modalHeading(__('checklist.summary.heading'))
            ->modalDescription(fn (array $arguments): string => self::summaryMode($arguments) === 'next'
                ? __('checklist.summary.description_next')
                : __('checklist.summary.description_save'))
            ->modalIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalIconColor('primary')
            ->modalWidth(Width::FourExtraLarge)
            ->schema(fn (): array => [
                Text::make(ChecklistSchema::summaryHtml($this->checklistState(), $this->checklistRecord())),
            ])
            ->modalSubmitActionLabel(fn (array $arguments): string => self::summaryMode($arguments) === 'next'
                ? __('checklist.summary.go_next')
                : __('checklist.summary.save'))
            ->modalCancelActionLabel(__('checklist.summary.go_back'))
            // Ileri mavi, Kaydet yesil (ActionColors standardi).
            ->modalSubmitAction(fn (Action $action, array $arguments): Action => $action->color(self::summaryMode($arguments) === 'next' ? ActionColors::VIEW : ActionColors::SAVE))
            ->extraModalFooterActions(fn (Action $action, array $arguments): array => self::summaryMode($arguments) === 'save' && DraftSupport::enabled()
                ? [
                    $action->makeModalSubmitAction('saveDraft', arguments: ['draft' => true])
                        ->label(__('checklist.summary.save_draft'))
                        ->color(ActionColors::NEUTRAL),
                ]
                : [])
            ->action(function (array $arguments): void {
                if (self::summaryMode($arguments) === 'next') {
                    $this->dispatch('next-wizard-step', key: (string) ($arguments['wizard'] ?? ''));

                    return;
                }

                $this->checklistConfirmed = true;
                $this->saveAsDraft = (bool) ($arguments['draft'] ?? false);
                $this->continueAfterChecklist();
            });
    }

    /**
     * Formdaki teklif tipini kaydetmeden once saklar.
     *
     * @param  array<string, mixed>  $data
     */
    protected function rememberOfferType(array $data): void
    {
        $value = $data['offer_type'] ?? null;
        $this->submittedOfferType = $value instanceof BackedEnum ? (string) $value->value : (is_string($value) ? $value : null);
    }

    /** GES 1.3 kurali teklif tipini Butcesel'e cevirdiyse kullaniciya soylenir. */
    protected function notifyBudgetarySwitch(?BusinessCase $case): void
    {
        if ($case === null || $this->submittedOfferType === null || $this->submittedOfferType === OfferType::Budgetary->value) {
            return;
        }

        if ($case->offer_type === OfferType::Budgetary) {
            Notification::make()
                ->title(__('checklist.messages.budgetary'))
                ->warning()
                ->send();
        }
    }

    /** Bu kaydetmede ozet gosterilir mi (olusturmada yalniz potansiyel is adiminin Kaydet'i). */
    protected function checklistSummaryOnSave(): bool
    {
        return true;
    }

    /**
     * Formun ham durumu (tahtanin ertelenmis cevaplari istekle gelmistir).
     *
     * @return array<string, mixed>
     */
    private function checklistState(): array
    {
        return is_array($this->data ?? null) ? $this->data : [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function summaryMode(array $arguments): string
    {
        return ($arguments['mode'] ?? 'save') === 'next' ? 'next' : 'save';
    }

    /** Pencerede onaydan sonra kaldigi kaydetmeyi surdurur (create / save). */
    abstract protected function continueAfterChecklist(): void;

    /** Duzenlenen potansiyel is (olusturmada null). */
    abstract protected function checklistRecord(): ?BusinessCase;
}
