<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Exports\ReferenceWorkbook;
use App\Models\Acquisition\ProjectReference;
use App\Query\Acquisition\ProjectReferenceQueries;
use App\Services\Acquisition\ProjectReferenceService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ScopeTypes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Referans tablosu (B50, D-177; 8 Ekim 2026 kullanici talimati: "Acilan
 * referans modal icinden ayni Filament tablo mantiginda filtreleme, arama
 * yapilabilir, Excel'e ona gore indirilebilir olur. Ayrica referans eklemesi de
 * bu modal icinde action butonda olur").
 *
 * Referanslar ekrani (ProjectReferenceResource) ve teklifteki referans
 * penceresi (App\Livewire\Acquisition\ReferenceListTable) ayni tabloyu kurar:
 * referans metni (aranir), proje tipi rozetleri (ProjectScopeType simgesi ve
 * rengi), Proje tipi ve Arsiv suzgecleri, ustte "Referans ekle" ve "Excel",
 * satirda Duzenle / Arsive al / Arsivden cikar (simge, D-125). Satira tiklamak
 * salt okunur ayrinti penceresini acar (RowDetail; referansin ayri sayfasi yok).
 * Excel gorunen (aranan / suzulen) satirlari kullanicinin bicimiyle verir
 * (ReferenceWorkbook); pencerede acilista teklifin tipleri secili gelir.
 */
final class ReferenceTable
{
    /** Referanslar ozelligi acik ve B50 uygulandi mi? */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B50') && FeatureFlags::enabled(Feature::References);
    }

    public static function excelEnabled(): bool
    {
        return self::enabled() && FeatureFlags::enabled(Feature::ReferenceExcel);
    }

    /**
     * D-183: Ayarlar > Referanslar ekraninda proje tipi suzgeci yerine sekmeler
     * vardir (ListProjectReferences::getTabs, $typeFilter = false); teklifteki
     * pencerede suzgec kalir ve acilista o kapsamin tipi secilidir.
     *
     * @param  list<string>  $presetTypes  pencerede acilista secili proje tipleri
     */
    public function configure(Table $table, array $presetTypes = [], bool $typeFilter = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(ProjectReferenceQueries::class)->ordered($query))
            ->columns([
                TextColumn::make('title')
                    ->label(__('project_reference.fields.title'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('scopeTypes.scope_type')
                    ->label(__('project_reference.fields.scope_types'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('archived_at')
                    ->label(__('project_reference.fields.archived_at'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...($typeFilter ? [
                    SelectFilter::make('scope_type')
                        ->label(__('project_reference.filters.scope_type'))
                        ->options(fn (): array => ScopeTypes::options())
                        ->multiple()
                        ->default($presetTypes === [] ? null : $presetTypes)
                        ->query(fn (Builder $query, array $data): Builder => app(ProjectReferenceQueries::class)->withTypes($query, (array) ($data['values'] ?? []))),
                ] : []),
                SelectFilter::make('archive')
                    ->label(__('project_reference.filters.archive'))
                    ->options([
                        'active' => __('project_reference.filters.archive_active'),
                        'archived' => __('project_reference.filters.archive_archived'),
                        'all' => __('project_reference.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(ProjectReferenceQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
            ])
            ->headerActions([
                $this->createAction(),
                $this->excelAction(),
            ])
            ->recordActions([
                RowDetail::action(),
                $this->editAction(),
                $this->archiveAction(),
                $this->restoreAction(),
            ])
            ->emptyStateHeading(__('project_reference.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTrophy)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * Referans formu (pencere): metin yarim satir, proje tipleri simgeli dugmeler.
     *
     * @return list<mixed>
     */
    public static function formComponents(?ProjectReference $record = null): array
    {
        return FieldGrid::modal([
            Textarea::make('title')
                ->label(__('project_reference.fields.title'))
                ->helperText(__('project_reference.help.title'))
                ->required()
                ->maxLength(500)
                ->rows(3),
            // D-163: tipler kendi simgesi ve rengiyle; secenekleri sutunlara dizilen liste.
            ToggleButtons::make('scope_types')
                ->label(__('project_reference.fields.scope_types'))
                ->helperText(__('project_reference.help.scope_types'))
                ->options(fn (): array => self::typeOptions($record))
                ->enum(ProjectScopeType::class)
                ->multiple()
                ->required()
                ->columns(['default' => 2, 'md' => 4])
                ->columnSpanFull(),
            Hidden::make('row_version'),
        ]);
    }

    public function createAction(): CreateAction
    {
        return CreateAction::make('create_reference')
            ->label(__('project_reference.actions.create'))
            ->model(ProjectReference::class)
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('project_reference.actions.create'))
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (): bool => self::enabled() && Gate::allows('create', ProjectReference::class))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(self::formComponents()))
            ->using(function (array $data): Model {
                try {
                    return app(ProjectReferenceService::class)->create($data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('project_reference.messages.created'));
    }

    public function editAction(): EditAction
    {
        return EditAction::make('edit_reference')
            ->label(__('project_reference.actions.edit'))
            ->modalHeading(__('project_reference.actions.edit'))
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (ProjectReference $record): bool => self::enabled() && Gate::allows('update', $record))
            ->schema(fn (Schema $schema, ProjectReference $record): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(self::formComponents($record)))
            ->fillForm(fn (ProjectReference $record): array => [
                'title' => $record->title,
                'scope_types' => array_map(static fn (ProjectScopeType $type): string => $type->value, $record->types()),
                'row_version' => $record->row_version,
            ])
            ->using(function (ProjectReference $record, array $data): Model {
                try {
                    return app(ProjectReferenceService::class)->update($record, $data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('project_reference.messages.saved'));
    }

    public function archiveAction(): Action
    {
        return Action::make('archive_reference')
            ->label(__('project_reference.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('project_reference.actions.archive'))
            ->modalDescription(__('project_reference.help.archive'))
            ->modalSubmitActionLabel(__('project_reference.actions.archive'))
            ->visible(fn (ProjectReference $record): bool => self::enabled() && ! $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (ProjectReference $record): void {
                try {
                    app(ProjectReferenceService::class)->archive($record);
                    DomainNotifications::success(__('project_reference.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore_reference')
            ->label(__('project_reference.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('project_reference.actions.restore'))
            ->modalDescription(__('project_reference.help.restore'))
            ->visible(fn (ProjectReference $record): bool => self::enabled() && $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (ProjectReference $record): void {
                try {
                    app(ProjectReferenceService::class)->restore($record);
                    DomainNotifications::success(__('project_reference.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Gorunen satirlarin Excel'i; sayfalar suzgecteki tiplerden, Referanslar
     * ekraninda (D-183) acik sekmenin tipinden; ikisi de yoksa butun gruplar.
     */
    public function excelAction(): Action
    {
        return Action::make('references_excel')
            ->label(__('project_reference.actions.excel'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (): bool => self::excelEnabled() && Gate::allows('viewAny', ProjectReference::class))
            ->action(function (HasTable $livewire): ?StreamedResponse {
                $query = $livewire->getFilteredSortedTableQuery();

                if ($query === null) {
                    return null;
                }

                $types = (array) ($livewire->getTableFilterState('scope_type')['values'] ?? []);
                $tab = property_exists($livewire, 'activeTab') ? ProjectScopeType::tryFrom((string) $livewire->activeTab) : null;

                if ($types === [] && $tab !== null) {
                    $types = [$tab->value];
                }

                return app(ReferenceWorkbook::class)->download($query->get(), $types);
            });
    }

    /**
     * Bir tipler listesinin Excel indirmesi (teklifteki "Indir" dugmesi).
     *
     * @param  list<string>  $types
     */
    public static function downloadFor(array $types): StreamedResponse
    {
        return app(ReferenceWorkbook::class)->download(app(ProjectReferenceQueries::class)->forExport($types), $types);
    }

    /**
     * Secilebilir tipler; duzenlenen referansin kayitli tipleri (or. ozellik
     * kapaliyken Otomasyon) secenekte kalir.
     *
     * @return array<string, string>
     */
    private static function typeOptions(?ProjectReference $record): array
    {
        $options = ScopeTypes::options();

        foreach ($record?->types() ?? [] as $type) {
            $options[$type->value] ??= (string) $type->getLabel();
        }

        return $options;
    }
}
