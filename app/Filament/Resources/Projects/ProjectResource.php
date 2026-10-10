<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects;

use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\ProjectOrigin;
use App\Enums\Project\ProjectStatus;
use App\Exceptions\AbstractException;
use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ProjectTypeCoordinatorSchema;
use App\Filament\Support\ProjectWizard;
use App\Filament\Support\RecordLinks;
use App\Models\Project\Project;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Project\ProjectTypeCoordinatorQueries;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectService;
use App\Support\Projects\ProjectNames;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Projeler (D-67, D-68, D-72). Goruntuleme sayfasi calisma alanidir
 * (ViewProject); olusturma ve duzenleme ayni adimli sihirbazi kullanir
 * (ProjectWizard: kimlik / saha / plan / fotograf + departman adimlari).
 * Proje uc yoldan dogar: devir kabulu, tekliften donusum, dogrudan olusturma.
 */
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('project.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project.plural');
    }

    /** D-174: baslik ve kirintilar kisa adla (yoksa lisans adi). */
    public static function getRecordTitle(?Model $record): string | Htmlable | null
    {
        return $record instanceof Project ? $record->display_name : parent::getRecordTitle($record);
    }

    /**
     * Genel arama (D-174): lisans adi ve kisa ad.
     *
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ProjectNames::schemaReady() ? ['name', 'short_name'] : ['name'];
    }

    /**
     * Kisa adla gorunen sonucta lisans adi da yazar.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $license = $record instanceof Project ? ProjectNames::licenseNameIfDifferent($record) : null;

        return $license === null ? [] : [__('project.fields.license_name') => $license];
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::Projects)
            && SchemaReadiness::hasBatch('B17')
            && SchemaReadiness::hasBatch('B17A')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        // Olusturma ve duzenleme sayfalari (HasWizard) adimlari ProjectWizard'dan
        // alir; bu tanim ayni adimlari tasiyan tek kaynaktir.
        return $schema->columns(1)->components([
            Wizard::make(app(ProjectWizard::class)->baseSteps())
                ->skippable()
                ->contained(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('businessCode.formatted_code')
                    ->label(__('project.fields.business_code'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                // D-174: tek sutunda kisa ad, altinda lisans adi; iki adla da aranir.
                TextColumn::make('name')
                    ->label(fn (): string => ProjectNames::shortNameEnabled() ? __('project.fields.names') : __('project.fields.name'))
                    ->formatStateUsing(fn (Project $record): string => $record->display_name)
                    ->description(fn (Project $record): ?string => ProjectNames::licenseNameIfDifferent($record))
                    ->limit(40)
                    ->searchable(query: fn (Builder $query, string $search): Builder => app(ProjectCatalogQueries::class)->searchByName($query, $search))
                    ->sortable(ProjectNames::schemaReady() ? ['short_name', 'name'] : ['name']),
                // D-174: proje tipi rozetleri (tipin rengi ve simgesi, teklif listesindeki gibi).
                TextColumn::make('scopes.scope_type')
                    ->label(__('project.fields.scope_types'))
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (): bool => ProjectNames::scopeTypesEnabled()),
                TextColumn::make('customerParty.display_name')
                    ->label(__('project.fields.customer_party'))
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('projectManager.full_name')
                    ->label(__('project.fields.project_manager'))
                    ->toggleable(),
                // D-175: projenin tiplerinin koordinatorleri ("GES: Ertugrul Sahin").
                TextColumn::make('project_type_coordinators')
                    ->label(__('project_type_coordinator.fields.project_coordinator'))
                    ->state(fn (Project $record): array => app(ProjectTypeCoordinatorSchema::class)->projectNames($record))
                    ->listWithLineBreaks()
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->placeholder('-')
                    ->visible(fn (): bool => ProjectNames::coordinatorsEnabled() && ProjectNames::scopeTypesEnabled())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('project.fields.status'))
                    ->badge(),
                TextColumn::make('primaryFocusWorkstream.group.name_tr')
                    ->label(__('project.fields.current_focus'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),
                TextColumn::make('origin')
                    ->label(__('project.fields.origin'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('site_city')
                    ->label(__('project.fields.site_city'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('current_macro_gate_code')
                    ->label(__('project.fields.current_macro_gate_code'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_finish_on')
                    ->label(__('project.fields.planned_finish_on'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('project.fields.status'))
                    ->options(ProjectStatus::class),
                SelectFilter::make('focus_group')
                    ->label(__('project.fields.current_focus'))
                    ->options(fn (): array => app(ProjectCatalogQueries::class)->operationGroupOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'primaryFocusWorkstream',
                            fn (Builder $workstream): Builder => $workstream->where('group_definition_id', (int) $value),
                        );
                    }),
                SelectFilter::make('origin')
                    ->label(__('project.fields.origin'))
                    ->options(ProjectOrigin::class),
                // D-175: secilen koordinatorun tiplerinden birini tasiyan projeler.
                SelectFilter::make('type_coordinator')
                    ->label(__('project_type_coordinator.fields.project_coordinator'))
                    ->options(fn (): array => app(ProjectTypeCoordinatorQueries::class)->coordinatorOptions())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : app(ProjectTypeCoordinatorQueries::class)->applyCoordinatorFilter($query, (int) $data['value']))
                    ->visible(fn (): bool => ProjectNames::coordinatorsEnabled() && ProjectNames::scopeTypesEnabled()),
                SelectFilter::make('criticality_profile')
                    ->label(__('project.fields.criticality_profile'))
                    ->options(CriticalityProfile::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('project.actions.open_workspace')),
                EditAction::make(),
                ActionGroup::make(self::statusActions())
                    ->label(__('project.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        // Iliskiler calisma alaninda ve duzenleme sihirbazinda departman adimlarina dagitilmistir.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private static function statusActions(): array
    {
        $actions = [];

        foreach (ProjectStatus::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('project.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('project.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (Project $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (Project $record, array $data) use ($target): void {
                    try {
                        app(ProjectService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('project.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
