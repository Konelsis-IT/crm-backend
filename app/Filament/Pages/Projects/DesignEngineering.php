<?php

declare(strict_types=1);

namespace App\Filament\Pages\Projects;

use App\Enums\Platform\Feature;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\CodeLine;
use App\Filament\Support\RecordLinks;
use App\Models\Project\Project;
use App\Query\Project\DesignEngineeringQueries;
use App\Services\Platform\FeatureFlags;
use App\Support\Projects\ProjectNames;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Operasyon - Taahhut > Projelendirme (D-234, 22 Ekim 2026; kullanici:
 * "Projelendirme sekmesinin olayi su olacak onlara verilen aktif devam eden
 * projeler takip edecekleri projeler gibi sekmeler olur. Ona ozel ozellikler
 * konustukca detaylanacak. Detay ekranini simdilik o listelerin yapma.").
 *
 * Projelendirme ekibinin taahhutleri sekmelerde (DesignEngineeringQueries);
 * ayri detay ekrani yok, satir taahhudun kendi sayfasini acar. Gorunurluk
 * taahhut listesiyle ayni (ProjectPolicy viewAny).
 */
class DesignEngineering extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsProject;

    // Taahhutler 10; Projelendirme hemen altinda.
    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'projelendirme';

    #[Url]
    public ?string $activeTab = null;

    public static function getNavigationLabel(): string
    {
        return __('design_engineering.nav');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::ProjectDesign)
            && DesignEngineeringQueries::enabled()
            && Gate::allows('viewAny', Project::class);
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    public function getTitle(): string | Htmlable
    {
        return __('design_engineering.title');
    }

    public function getSubheading(): ?string
    {
        return __('design_engineering.subheading');
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(DesignEngineeringQueries::class);
        $counts = $queries->counts();
        $tab = fn (string $key, Heroicon $icon): Tab => Tab::make(__('design_engineering.tabs.'.$key))
            ->icon($icon)
            ->badge($counts[$key] ?: null)
            ->modifyQueryUsing(fn (Builder $query): Builder => $queries->forTab($query, $key));

        return [
            DesignEngineeringQueries::TAB_ACTIVE => $tab(DesignEngineeringQueries::TAB_ACTIVE, Heroicon::OutlinedPlay),
            DesignEngineeringQueries::TAB_UPCOMING => $tab(DesignEngineeringQueries::TAB_UPCOMING, Heroicon::OutlinedClock),
            DesignEngineeringQueries::TAB_FOLLOW => $tab(DesignEngineeringQueries::TAB_FOLLOW, Heroicon::OutlinedEye),
            DesignEngineeringQueries::TAB_ALL => $tab(DesignEngineeringQueries::TAB_ALL, Heroicon::OutlinedSquares2x2),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(DesignEngineeringQueries::class)->base())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->columns([
                CodeLine::under(
                    TextColumn::make('name')
                        ->label(__('design_engineering.columns.project'))
                        ->formatStateUsing(fn (Project $record): string => $record->display_name)
                        ->limit(40)
                        ->searchable(ProjectNames::schemaReady() ? ['short_name', 'name'] : ['name'])
                        ->sortable(ProjectNames::schemaReady() ? ['short_name', 'name'] : ['name']),
                    'businessCode.formatted_code',
                ),
                TextColumn::make('scopes.scope_type')
                    ->label(__('design_engineering.columns.scope_types'))
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (): bool => ProjectNames::scopeTypesEnabled()),
                TextColumn::make('customerParty.display_name')
                    ->label(__('design_engineering.columns.customer'))
                    ->limit(40)
                    ->wrap()
                    ->width('14rem')
                    ->placeholder('-')
                    ->toggleable(),
                // Ilisik kayit simgesi gelmesin diye duz ad (TableConventions noktali adlara simge koyar).
                TextColumn::make('design_status')
                    ->label(__('design_engineering.columns.design_status'))
                    ->state(fn (Project $record): mixed => $record->designWorkstream?->status)
                    ->badge(),
                TextColumn::make('designWorkstream.owner.full_name')
                    ->label(__('design_engineering.columns.owner'))
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->placeholder('-'),
                TextColumn::make('design_plan')
                    ->label(__('design_engineering.columns.plan'))
                    ->state(function (Project $record): ?string {
                        $stream = $record->designWorkstream;
                        $start = $stream?->planned_start_on?->format('d.m.Y');
                        $finish = $stream?->planned_finish_on?->format('d.m.Y');

                        return $start === null && $finish === null ? null : ($start ?? '…').' – '.($finish ?? '…');
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('design_progress')
                    ->label(__('design_engineering.columns.progress'))
                    ->state(fn (Project $record): ?string => $record->designWorkstream?->progress_pct !== null ? '%'.(int) $record->designWorkstream->progress_pct : null)
                    ->alignEnd()
                    ->placeholder('-'),
                TextColumn::make('design_open_work')
                    ->label(__('design_engineering.columns.open_work'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('projectManager.full_name')
                    ->label(__('design_engineering.columns.manager'))
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('design_engineering.columns.project_status'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Ayri detay ekrani yok (kullanici): satir taahhudun kendi sayfasini acar.
            ->recordUrl(fn (Project $record): ?string => ProjectResource::canView($record) ? ProjectResource::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading(__('design_engineering.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPencilSquare)
            ->defaultSort(ProjectNames::schemaReady() ? 'short_name' : 'name')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
