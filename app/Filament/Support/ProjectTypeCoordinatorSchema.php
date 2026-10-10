<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Query\Project\ProjectTypeCoordinatorQueries;
use App\Support\Projects\ProjectNames;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;

/**
 * Proje tipi koordinatorlerinin ekranlardaki gorunumu (B49, D-175; kullanici:
 * "Personel detayinda, kartlarinda vs. de gorulsun"). Rozet tipin kendi
 * simgesi ve rengiyle (D-163) "GES koordinatoru" yazar. Her sey
 * `projects.type_coordinators` ozelligine ve B49'a baglidir
 * (ProjectNames::coordinatorsEnabled()).
 */
final class ProjectTypeCoordinatorSchema
{
    public function __construct(private readonly ProjectTypeCoordinatorQueries $queries) {}

    /**
     * Personelin koordinator oldugu tipler (kart ve detay karti rozetleri).
     *
     * @return list<Text>
     */
    public function personnelBadges(Personnel $personnel): array
    {
        if (! ProjectNames::coordinatorsEnabled()) {
            return [];
        }

        return array_map(
            static fn (ProjectScopeType $type): Text => Text::make(self::badgeLabel($type))
                ->badge()
                ->color($type->getColor())
                ->icon($type->getIcon()),
            $this->queries->typesFor((int) $personnel->getKey()),
        );
    }

    /**
     * Personelin koordinator oldugu tipler (liste sutunu icin).
     *
     * @return list<ProjectScopeType>
     */
    public function personnelTypes(Personnel $personnel): array
    {
        return ProjectNames::coordinatorsEnabled() ? $this->queries->typesFor((int) $personnel->getKey()) : [];
    }

    /**
     * Proje sayfasi: projenin tiplerinin koordinatorleri ("GES: Ertugrul
     * Sahin"). Tek koordinator varsa adi personel kartina gider.
     */
    public function projectEntry(Project $project): TextEntry
    {
        $items = ProjectNames::coordinatorsEnabled() && ProjectNames::scopeTypesEnabled() ? $this->queries->forProject($project) : [];
        $lines = array_map(static fn (array $item): string => self::projectItem($item['type'], (string) $item['coordinator']->personnel?->full_name), $items);
        $single = count($items) === 1 ? $items[0]['coordinator']->personnel : null;

        return TextEntry::make('project_type_coordinators')
            ->label(__('project_type_coordinator.fields.project_coordinators'))
            ->state($lines)
            ->listWithLineBreaks()
            ->icon(RecordLinks::PERSONNEL_ICON)
            ->iconColor('primary')
            ->color('primary')
            ->weight(FontWeight::SemiBold)
            ->url($single instanceof Personnel ? RecordLinks::detailUrl($single, checkRecord: false) : null)
            ->visible($lines !== []);
    }

    /**
     * Proje listesi sutunu: projenin tiplerinin koordinator adlari.
     *
     * @return list<string>
     */
    public function projectNames(Project $project): array
    {
        if (! ProjectNames::coordinatorsEnabled()) {
            return [];
        }

        return array_map(static fn (array $item): string => self::projectItem($item['type'], (string) $item['coordinator']->personnel?->full_name), $this->queries->forProject($project));
    }

    public static function badgeLabel(ProjectScopeType $type): string
    {
        return (string) __('project_type_coordinator.values.badge', ['type' => $type->getLabel()]);
    }

    private static function projectItem(ProjectScopeType $type, string $name): string
    {
        return (string) __('project_type_coordinator.values.project_item', ['type' => $type->getLabel(), 'name' => $name]);
    }
}
