<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\PersonnelSelect;
use App\Models\Project\ProjectTypeCoordinator;
use App\Query\Project\ProjectTypeCoordinatorQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Project\ProjectTypeCoordinatorService;
use App\Support\Projects\ProjectNames;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Projeler listesindeki "Proje tipi koordinatorleri" dugmesi (D-206; kullanici
 * 14 Ekim 2026: "Proje tipi koordinatorlerini projeler listesine action buton
 * olarak ekle oradan modal olarak listelensin sol menuden kalksin"). Ayri
 * ekran (D-175) kalkti; pencerede her proje tipinin koordinatoru, atandigi
 * tarih ve proje sayisi listelenir. Yetkili kisi koordinatoru secip degistirir
 * ya da secimi bosaltip kaldirir; Kaydet hepsini tek islemde yazar
 * (ProjectTypeCoordinatorService::sync). Yetkisi olmayan yalniz gorur.
 */
final class ProjectTypeCoordinatorsAction
{
    public static function make(): Action
    {
        return Action::make('typeCoordinators')
            ->label(__('project_type_coordinator.nav'))
            ->icon(Heroicon::OutlinedUserGroup)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (): bool => self::canView())
            ->modalHeading(__('project_type_coordinator.title'))
            ->modalDescription(__('project_type_coordinator.subheading'))
            ->modalIcon(Heroicon::OutlinedUserGroup)
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (): array => [
                'coordinators' => array_map(
                    static fn (array $row): ?int => $row['personnel_id'],
                    app(ProjectTypeCoordinatorQueries::class)->overview(),
                ),
            ])
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal(self::fields())))
            ->modalSubmitActionLabel(__('project_type_coordinator.actions.save'))
            // Kaydet yesil (D-148 ortak kurali) ve yalniz yetkiliye gorunur.
            ->modalSubmitAction(fn (Action $action): Action => ActionColors::submit($action)->visible(self::canManage()))
            ->modalCancelActionLabel(fn (): ?string => self::canManage() ? null : __('project_type_coordinator.actions.close'))
            ->action(function (array $data): void {
                $assignments = array_map(
                    static fn (mixed $id): ?int => filled($id) ? (int) $id : null,
                    (array) ($data['coordinators'] ?? []),
                );

                try {
                    $changed = app(ProjectTypeCoordinatorService::class)->sync($assignments);
                    DomainNotifications::success(__($changed > 0 ? 'project_type_coordinator.messages.saved' : 'project_type_coordinator.messages.unchanged'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function canView(): bool
    {
        return FeatureFlags::enabled(Feature::ProjectTypeCoordinators)
            && ProjectNames::coordinatorsEnabled()
            && ProjectNames::scopeTypesEnabled()
            && Gate::allows('viewAny', ProjectTypeCoordinator::class);
    }

    private static function canManage(): bool
    {
        return Gate::allows('create', ProjectTypeCoordinator::class);
    }

    /**
     * Her proje tipi icin koordinator secimi; altinda atandigi tarih ve proje sayisi.
     *
     * @return list<Select>
     */
    private static function fields(): array
    {
        $fields = [];

        foreach (app(ProjectTypeCoordinatorQueries::class)->overview() as $value => $row) {
            $type = $row['type'];

            $fields[] = Select::make('coordinators.'.$value)
                ->label($type instanceof ProjectScopeType ? (string) $type->getLabel() : (string) $value)
                ->prefixIcon($type instanceof ProjectScopeType ? $type->getIcon() : null)
                ->helperText(self::summary($row))
                ->placeholder(__('project_type_coordinator.values.none'))
                ->tap(PersonnelSelect::active())
                ->searchable()
                ->native(false)
                ->disabled(! self::canManage());
        }

        return $fields;
    }

    /**
     * "01.10.2026 tarihinden beri · 5 proje"
     *
     * @param  array<string, mixed>  $row
     */
    private static function summary(array $row): string
    {
        $projects = __('project_type_coordinator.values.projects', ['count' => (int) $row['project_count']]);

        return $row['since'] instanceof Carbon
            ? __('project_type_coordinator.values.since', ['date' => $row['since']->format('d.m.Y')]).' · '.$projects
            : $projects;
    }
}
