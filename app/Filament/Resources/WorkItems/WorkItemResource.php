<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems;

use App\Filament\NavigationGroup;
use App\Filament\Resources\WorkItems\Pages\CreateWorkItem;
use App\Filament\Resources\WorkItems\Pages\EditWorkItem;
use App\Filament\Resources\WorkItems\Pages\ListWorkItems;
use App\Filament\Resources\WorkItems\Pages\ViewWorkItem;
use App\Filament\Resources\WorkItems\Schemas\WorkItemForm;
use App\Filament\Resources\WorkItems\Schemas\WorkItemInfolist;
use App\Filament\Resources\WorkItems\Tables\WorkItemTable;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Raporlar > Isler (B36, D-115; Filament tablosu): butun kartlarin tablosu.
 * Pano ile ayni veriyi gosterir; suzgec, siralama, sutun secimi, gruplama ve
 * toplu islem buradadir. Satira tiklayinca kartin detay sayfasi ve gecmisi
 * acilir. Kisi kendi kartlarini, amir ekibinin, yetkili butun kartlari gorur.
 */
class WorkItemResource extends Resource
{
    protected static ?string $model = WorkItem::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'isler';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('work_item.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('work_item.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('work_item.nav.items');
    }

    public static function canAccess(): bool
    {
        return SchemaReadiness::hasBatch('B36') && parent::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return app(WorkItemQueries::class)->applyVisible(
            parent::getEloquentQuery()->with(app(WorkItemQueries::class)->relations()),
            $user instanceof Personnel ? $user : null,
        );
    }

    public static function form(Schema $schema): Schema
    {
        return WorkItemForm::make($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkItemInfolist::make($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkItemTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkItems::route('/'),
            'create' => CreateWorkItem::route('/create'),
            'view' => ViewWorkItem::route('/{record}'),
            'edit' => EditWorkItem::route('/{record}/edit'),
        ];
    }
}
