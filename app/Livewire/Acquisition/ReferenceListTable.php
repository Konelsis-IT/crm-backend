<?php

declare(strict_types=1);

namespace App\Livewire\Acquisition;

use App\Enums\Platform\Feature;
use App\Filament\Support\ReferenceTable;
use App\Models\Acquisition\ProjectReference;
use App\Query\Acquisition\ProjectReferenceQueries;
use App\Services\Platform\FeatureFlags;
use App\Support\Acquisition\ScopeTypes;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Teklifteki referans penceresinin tablosu (B50, D-177; "acildiginda modalda
 * ilgili proje tipine uygun tum referanslar gorulsun"). Referanslar ekraniyla
 * ayni tablo (ReferenceTable): arama, proje tipi ve arsiv suzgeci, Excel,
 * "Referans ekle". Acilista teklifin proje tipleri suzgecte secili gelir.
 * Pano widget'lari arasinda kesfedilmesin diye app/Filament/Widgets disindadir.
 */
class ReferenceListTable extends TableWidget
{
    /** @var list<string> Teklifin proje tipleri (acilis suzgeci). */
    public array $types = [];

    protected int | string | array $columnSpan = 'full';

    public function mount(): void
    {
        abort_unless(
            ReferenceTable::enabled() && FeatureFlags::enabled(Feature::ProposalReferences) && Gate::allows('viewAny', ProjectReference::class),
            403,
        );

        $this->types = ScopeTypes::values($this->types);
    }

    public function table(Table $table): Table
    {
        return app(ReferenceTable::class)->configure(
            $table
                ->query(fn (): Builder => app(ProjectReferenceQueries::class)->tableQuery())
                ->heading(__('project_reference.list.table_heading')),
            $this->types,
        );
    }
}
