<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Pages;

use App\Enums\Party\PartyRoleCode;
use App\Filament\Resources\Parties\PartyResource;
use App\Query\Party\PartyQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Taraf listesi (D-95, 16 Eylul 2026 kullanici istegi): tek tablo yerine
 * taraf tipine gore sekmeler. Bir taraf birden fazla tipe sahip olabilir
 * (ayni firma hem musteri hem tedarikci); bu yuzden sekmeler suzgectir,
 * ayrik kumeler degildir ve kayit birden fazla sekmede gorunebilir.
 *
 * Sekmede yalniz acik (bitis tarihi olmayan) tipler sayilir.
 */
class ListParties extends ListRecords
{
    protected static string $resource = PartyResource::class;

    /** @var array<string, int>|null */
    private ?array $counts = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->roleCounts();

        $tabs = [
            'all' => Tab::make(__('party.tabs.all'))
                ->badge(app(PartyQueries::class)->total()),
        ];

        foreach (PartyRoleCode::cases() as $code) {
            $tabs[$code->value] = Tab::make($code->getLabel())
                ->badge($counts[$code->value] ?? 0)
                ->modifyQueryUsing(fn (Builder $query): Builder => app(PartyQueries::class)->withRole($query, $code));
        }

        return $tabs;
    }

    /**
     * Tip basina acik taraf sayisi; tek sorguda toplanir.
     *
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        return $this->counts ??= app(PartyQueries::class)->roleCounts();
    }
}
