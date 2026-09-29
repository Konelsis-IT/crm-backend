<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Enums\Acquisition\OfferStatus;
use App\Filament\Exports\ProposalExporter;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\ExportActions;
use App\Query\Acquisition\ProposalQueries;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProposals extends ListRecords
{
    protected static string $resource = ProposalResource::class;

    /** @var array<string, int>|null */
    private ?array $counts = null;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(ProposalExporter::class),
            CreateAction::make(),
        ];
    }

    /**
     * Teklif durumuna gore sekmeler (D-136, 28 Eylul 2026 kullanici istegi):
     * Tumu / Verilen Teklifler / Verilecek Teklifler / Kacan Firsat. Teklif
     * durumu B29 ile geldi; oncesinde sekme yoktur.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            return [];
        }

        $queries = app(ProposalQueries::class);
        $this->counts ??= $queries->offerStatusCounts();

        $tabs = [
            'all' => Tab::make(__('proposal.tabs.all'))->badge($queries->total()),
        ];

        foreach ([
            'submitted' => OfferStatus::Submitted,
            'to_be_submitted' => OfferStatus::ToBeSubmitted,
            'lost' => OfferStatus::Lost,
        ] as $key => $status) {
            $tabs[$key] = Tab::make(__('proposal.tabs.'.$key))
                ->badge($this->counts[$status->value] ?? 0)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->withOfferStatus($query, $status));
        }

        return $tabs;
    }
}
