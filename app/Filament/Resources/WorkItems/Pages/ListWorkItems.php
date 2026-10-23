<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Pages;

use App\Filament\Exports\WorkItemExporter;
use App\Filament\Resources\WorkItems\WorkItemActions;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\ExportActions;
use App\Filament\Support\WorkAppConfig;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

/**
 * Raporlar > Isler (B36, D-115): butun kartlarin tablosu. Akilli is ekle
 * acikken (D-235) Is ekle ayni Akilli is ekle penceresini acar; pencere
 * kaydedince ("kq-saved") liste yenilenir.
 */
class ListWorkItems extends ListRecords
{
    protected static string $resource = WorkItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WorkItemActions::openBoard(),
            ExportActions::table(WorkItemExporter::class),
            WorkAppConfig::smartOn()
                ? WorkItemActions::smartCreate()
                : CreateAction::make()->label(__('work_item.actions.create')),
        ];
    }

    /**
     * Akilli is penceresi (bu sayfada ya da ust cubukta) kaydetti: bilesen yeniden
     * cizilir, tablo guncel kayitlari okur (sayfa ve suzgecler korunur).
     */
    #[On('kq-saved')]
    public function refreshAfterSmartSave(): void {}

    /**
     * D-240 (kullanici 23 Ekim 2026: "Isler kisminin arayuzunu react tablosu ile
     * yapalim. Filament bunun icin uygun degil"): Akilli is ekle acikken liste
     * React'tir (resources/js/work/work-list.js); kapaliyken Filament tablosu.
     */
    public function content(Schema $schema): Schema
    {
        if (! WorkAppConfig::smartOn()) {
            return parent::content($schema);
        }

        return $schema->components([
            View::make('filament.work.root')->viewData(['root' => 'work-list', 'config' => WorkAppConfig::workList()]),
        ]);
    }
}
