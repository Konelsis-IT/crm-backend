<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Livewire\Acquisition\ReferenceListTable;
use App\Models\Acquisition\ProjectReference;
use App\Services\Platform\FeatureFlags;
use Filament\Actions\Action;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teklifteki referanslar (B50, D-177; ozellik acquisition.references.proposal_field).
 *
 * D-183 (9 Ekim 2026 kullanici talimati: "Teklifteki referans listesi kismini
 * GES kapsami, BESS kapsami schemalarinin action buton kismina ekle. Referanslar
 * butonu olsun, hemen yaninda da indir iconu olsun, o kadar."): ayri "Referans
 * listesi" alani ve karti kaldirildi. Teklifin her kapsam bolumunun basliginda
 * (olustur / duzenle adimi ve teklif sayfasindaki kapsam karti) iki kucuk eylem:
 *
 * - "Referanslar": o proje tipine ayarli referans penceresi; Referanslar
 *   ekraniyla ayni tablo (ReferenceListTable: arama, proje tipi suzgeci, Excel,
 *   Referans ekle).
 * - Indir simgesi ("Referanslari indir"): o tipin referanslari kullanicinin
 *   Excel bicimiyle (ReferenceTable::downloadFor).
 */
final class ReferenceListField
{
    public static function enabled(): bool
    {
        return ReferenceTable::enabled()
            && FeatureFlags::enabled(Feature::ProposalReferences)
            && Gate::allows('viewAny', ProjectReference::class);
    }

    /**
     * Kapsam bolumu basligindaki eylemler: "Referanslar" ve indir simgesi.
     * $prefix ayni sayfada iki ayri yerde (form / kart) ad cakismasin diye.
     *
     * @return list<Action>
     */
    public function scopeHeaderActions(ProjectScopeType $type, string $prefix = 'scope'): array
    {
        $types = [$type->value];

        return [
            $this->openAction($types, $prefix.'_references_'.$type->value)
                ->label(__('project_reference.actions.references'))
                ->modalHeading(__('project_reference.list.modal_heading_type', ['type' => (string) $type->getLabel()]))
                ->button()
                ->size(Size::Small)
                ->color(ActionColors::NEUTRAL)
                ->visible(fn (): bool => self::enabled()),
            $this->downloadAction($types, $prefix.'_references_download_'.$type->value)
                ->iconButton()
                ->tooltip(__('project_reference.actions.download_type'))
                ->visible(fn (): bool => self::enabled() && ReferenceTable::excelEnabled()),
        ];
    }

    /**
     * Referans penceresi: Referanslar ekraniyla ayni tablo, acilista $types secili.
     *
     * @param  list<string>  $types
     */
    public function openAction(array $types, string $name = 'open_reference_list'): Action
    {
        return Action::make($name)
            ->label(__('project_reference.actions.open_list'))
            ->icon(Heroicon::OutlinedTrophy)
            ->modalHeading(__('project_reference.list.modal_heading'))
            ->modalDescription(__('project_reference.list.modal_description'))
            ->modalIcon(Heroicon::OutlinedTrophy)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('project_reference.actions.close'))
            ->schema([
                Livewire::make(ReferenceListTable::class, ['types' => $types])
                    ->key('reference-list-'.implode('-', $types)),
            ]);
    }

    /**
     * $types tiplerinin referanslarinin Excel indirmesi.
     *
     * @param  list<string>  $types
     */
    public function downloadAction(array $types, string $name = 'download_reference_list'): Action
    {
        return Action::make($name)
            ->label(__('project_reference.actions.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (): bool => ReferenceTable::excelEnabled() && $types !== [])
            ->action(fn (): StreamedResponse => ReferenceTable::downloadFor($types));
    }
}
