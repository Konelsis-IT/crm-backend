<?php

declare(strict_types=1);

namespace App\Filament\Pages\Projects;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\RecordLinks;
use App\Models\Project\ProjectTypeCoordinator;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Project\ProjectTypeCoordinatorQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Project\ProjectTypeCoordinatorService;
use App\Support\Projects\ProjectNames;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Proje Grubu > Proje tipi koordinatorleri (B49, D-175; 8 Ekim 2026 kullanici
 * talimati: "Proje mudurlerinden bagimsiz olarak proje tipinin tamamini
 * koordine edenler var ... Sisteme bir Proje tipi koordinatoru ekleyelim").
 *
 * Her proje tipi (simgesi ve rengiyle, D-163) bir satirdir; atanmamis tip de
 * gorunur. Satira tiklamak koordinatorun personel kartini acar (tablo kurali
 * D-125); koordinatoru olmayan satirda tiklama atama penceresini acar. Satir
 * eylemleri yalniz simge: Koordinator ata / degistir (turuncu), Koordinatoru
 * kaldir (iptal rengi, onayli). Yazma ProjectTypeCoordinatorService ile;
 * atama gecmisi silinmez.
 */
class ProjectTypeCoordinators extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'proje-tipi-koordinatorleri';

    public static function getNavigationLabel(): string
    {
        return __('project_type_coordinator.nav');
    }

    public function getTitle(): string | Htmlable
    {
        return __('project_type_coordinator.title');
    }

    public function getSubheading(): ?string
    {
        return __('project_type_coordinator.subheading');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::ProjectTypeCoordinators)
            && ProjectNames::coordinatorsEnabled()
            && ProjectNames::scopeTypesEnabled()
            && Gate::allows('viewAny', ProjectTypeCoordinator::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(ProjectTypeCoordinatorQueries::class)->overview())
            ->paginated(false)
            ->columns([
                TextColumn::make('type')
                    ->label(__('project_type_coordinator.columns.type'))
                    ->badge()
                    ->weight('medium'),
                TextColumn::make('personnel_name')
                    ->label(__('project_type_coordinator.columns.coordinator'))
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->color(fn (array $record): string => $record['personnel_id'] !== null ? 'primary' : 'gray')
                    ->weight('medium')
                    ->placeholder(__('project_type_coordinator.values.none')),
                TextColumn::make('since')
                    ->label(__('project_type_coordinator.columns.since'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('project_count')
                    ->label(__('project_type_coordinator.columns.project_count'))
                    ->formatStateUsing(fn (mixed $state): string => __('project_type_coordinator.values.projects', ['count' => (int) $state]))
                    ->color('gray'),
            ])
            // D-125: satir koordinatorun personel kartina gider; koordinatoru yoksa atama penceresi acilir.
            ->recordUrl(fn (array $record): ?string => $this->personnelUrl($record))
            ->recordAction('assign')
            ->recordActions([
                $this->assignAction(),
                $this->removeAction(),
            ])
            ->emptyStateHeading(__('project_type_coordinator.empty'));
    }

    private function assignAction(): Action
    {
        return Action::make('assign')
            ->label(fn (array $record): string => $record['personnel_id'] === null
                ? __('project_type_coordinator.actions.assign')
                : __('project_type_coordinator.actions.change'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color(ActionColors::EDIT)
            ->modalHeading(fn (array $record): string => __('project_type_coordinator.modals.assign_heading', ['type' => self::typeLabel($record)]))
            ->modalIcon(fn (array $record): ?Heroicon => $record['type'] instanceof ProjectScopeType ? $record['type']->getIcon() : null)
            ->fillForm(fn (array $record): array => ['personnel_id' => $record['personnel_id']])
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                Select::make('personnel_id')
                    ->label(__('project_type_coordinator.fields.personnel'))
                    ->helperText(__('project_type_coordinator.help.personnel'))
                    ->options(fn (): array => app(PersonnelQueries::class)->activePersonnelOptions())
                    ->searchable()
                    ->required()
                    ->native(false),
            ])))
            ->visible(fn (): bool => Gate::allows('create', ProjectTypeCoordinator::class))
            ->action(function (array $record, array $data): void {
                try {
                    app(ProjectTypeCoordinatorService::class)->assign($record['type'], (int) $data['personnel_id']);
                    $this->flushCachedTableRecords();
                    DomainNotifications::success(__('project_type_coordinator.messages.assigned'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    private function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('project_type_coordinator.actions.remove'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color(ActionColors::CANCEL)
            ->requiresConfirmation()
            ->modalHeading(fn (array $record): string => __('project_type_coordinator.modals.remove_heading', ['type' => self::typeLabel($record)]))
            ->modalDescription(fn (array $record): string => __('project_type_coordinator.modals.remove_description', ['name' => (string) $record['personnel_name']]))
            ->visible(fn (array $record): bool => $record['personnel_id'] !== null && Gate::allows('create', ProjectTypeCoordinator::class))
            ->action(function (array $record): void {
                try {
                    app(ProjectTypeCoordinatorService::class)->remove($record['type']);
                    $this->flushCachedTableRecords();
                    DomainNotifications::success(__('project_type_coordinator.messages.removed'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function personnelUrl(array $record): ?string
    {
        if ($record['personnel_id'] === null || ! PersonnelResource::canAccess()) {
            return null;
        }

        // Detay sayfasi kendi yetkisini denetler (D-118 ilkesi, RecordLinks).
        return PersonnelResource::getUrl('view', ['record' => (int) $record['personnel_id']]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private static function typeLabel(array $record): string
    {
        return $record['type'] instanceof ProjectScopeType ? (string) $record['type']->getLabel() : (string) $record['type'];
    }
}
