<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\RelationManagers;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FileLinks;
use App\Models\Report\WorkItem;
use App\Models\Report\WorkItemFile;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Report\WorkItemService;
use App\Support\UploadLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * Isin belgeleri (D-219, B57; kullanici 16 Ekim 2026: "iş model yapımıza çoklu
 * belge eklenebilme sistemi eklenecek"). Birden fazla dosya birlikte eklenir;
 * satira tiklamak belgeyi yeni sekmede acar. Kaldirmak yalniz bu isten
 * baglantiyi siler; dosya baska kayitta kullaniliyorsa orada kalir.
 */
class WorkItemFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPaperClip;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return FeatureFlags::enabled(Feature::WorkItemsExtended)
            && WorkItemQueries::extended()
            && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_item.files.title');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord instanceof WorkItem ? $ownerRecord->files()->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('work_item.files.title'))
            ->description(__('work_item.files.help'))
            ->columns([
                TextColumn::make('fileObject.original_name')
                    ->label(__('work_item.files.name'))
                    ->icon(Heroicon::OutlinedDocument)
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('fileObject.byte_size')
                    ->label(__('work_item.files.size'))
                    ->formatStateUsing(fn ($state): string => Number::fileSize((int) $state, precision: 1))
                    ->placeholder('–'),
                TextColumn::make('created_at')
                    ->label(__('work_item.files.added_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('fileObject'))
            ->recordUrl(fn (WorkItemFile $record): string => FileLinks::workItemFile($record), shouldOpenInNewTab: true)
            ->headerActions([
                Action::make('add_files')
                    ->label(__('work_item.files.add'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->modalWidth('2xl')
                    ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                        FileUpload::make('files')
                            ->label(__('work_item.files.files'))
                            ->helperText(__('work_item.files.files_help', ['size' => UploadLimits::readable(UploadLimits::documentMaxKb())]))
                            ->multiple()
                            ->disk('local')
                            ->directory('document-uploads-tmp')
                            ->storeFileNamesIn('file_names')
                            ->maxSize(UploadLimits::documentMaxKb())
                            ->maxFiles(20)
                            ->required()
                            ->columnSpan(FieldGrid::MODAL_LONG),
                        Hidden::make('file_names'),
                    ])))
                    ->action(function (array $data): void {
                        $names = (array) ($data['file_names'] ?? []);
                        $uploads = [];

                        foreach (array_values((array) ($data['files'] ?? [])) as $path) {
                            $uploads[(string) $path] = isset($names[$path]) ? (string) $names[$path] : null;
                        }

                        try {
                            $count = app(WorkItemService::class)->addFiles($this->getOwnerRecord(), $uploads);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }

                        DomainNotifications::success(__('work_item.files.added', ['count' => $count]));
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('work_item.files.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (WorkItemFile $record): string => FileLinks::workItemFile($record, 'download')),
                Action::make('remove')
                    ->label(__('work_item.files.remove'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('work_item.files.remove_heading'))
                    ->modalDescription(__('work_item.files.remove_help'))
                    ->visible(fn (WorkItemFile $record): bool => Gate::allows('delete', $record))
                    ->action(function (WorkItemFile $record): void {
                        try {
                            app(WorkItemService::class)->removeFile($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }

                        DomainNotifications::success(__('work_item.files.removed'));
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->paginated(false)
            ->emptyStateHeading(__('work_item.files.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }
}
