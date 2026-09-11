<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FileLinks;
use App\Models\Project\ProjectPhoto;
use App\Models\Project\ProjectWorkstream;
use App\Services\Project\ProjectPhotoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Saha fotograflari: coklu yukleme (DMS file_objects), kapak secimi,
 * adim (departman) etiketi. Dosya islemleri ProjectPhotoService'te.
 */
class PhotosRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'photos';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPhoto;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_photo.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_photo.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('caption')
                        ->label(__('project_photo.fields.caption'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    DatePicker::make('taken_on')
                        ->label(__('project_photo.fields.taken_on'))
                        ->displayFormat('d.m.Y'),
                    $this->workstreamSelect(),
                    Toggle::make('is_cover')
                        ->label(__('project_photo.fields.is_cover'))
                        ->helperText(__('project_photo.help.is_cover')),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('project_photo.relation.title'))
            ->recordTitleAttribute('caption')
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->getStateUsing(fn (ProjectPhoto $record): ?string => $record->previewUrl('thumbnail'))
                    ->imageHeight(72)
                    ->square(),
                TextColumn::make('caption')
                    ->label(__('project_photo.fields.caption'))
                    ->limit(40)
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('workstream.group.name_tr')
                    ->label(__('project_photo.fields.workstream'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('taken_on')
                    ->label(__('project_photo.fields.taken_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('is_cover')
                    ->label(__('project_photo.fields.is_cover'))
                    ->boolean(),
                TextColumn::make('file.original_name')
                    ->label(__('project_photo.fields.file'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('project_photo.fields.uploaded_at'))
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('project_photo.actions.upload'))
                    ->icon(Heroicon::OutlinedCamera)
                    ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                        FileUpload::make('files')
                            ->label(__('project_photo.fields.files'))
                            ->helperText(__('project_photo.help.files'))
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('local')
                            ->directory('document-uploads-tmp')
                            ->storeFileNamesIn('file_original_names')
                            ->maxSize(8192)
                            ->required()
                            ->columnSpanFull(),
                        Hidden::make('file_original_names'),
                        TextInput::make('caption')
                            ->label(__('project_photo.fields.caption'))
                            ->maxLength(255),
                        DatePicker::make('taken_on')
                            ->label(__('project_photo.fields.taken_on'))
                            ->displayFormat('d.m.Y'),
                        $this->workstreamSelect(),
                        Toggle::make('is_cover')
                            ->label(__('project_photo.fields.is_cover'))
                            ->helperText(__('project_photo.help.is_cover')),
                    ])))
                    ->using(function (array $data): Model {
                        $paths = array_values((array) ($data['files'] ?? []));
                        $names = (array) ($data['file_original_names'] ?? []);
                        $service = app(ProjectPhotoService::class);
                        $last = null;

                        try {
                            foreach ($paths as $index => $path) {
                                $last = $service->create([
                                    'project_id' => $this->getOwnerRecord()->getKey(),
                                    'caption' => $data['caption'] ?? null,
                                    'taken_on' => $data['taken_on'] ?? null,
                                    'workstream_id' => $data['workstream_id'] ?? null,
                                    'is_cover' => $index === 0 && (bool) ($data['is_cover'] ?? false),
                                    'file_temp_path' => $path,
                                    'file_original_name' => $names[$path] ?? $names[$index] ?? null,
                                ]);
                            }
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }

                        if ($last === null) {
                            throw new Halt;
                        }

                        DomainNotifications::success(__('project_photo.messages.uploaded', ['count' => count($paths)]));

                        return $last;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectPhoto $record, array $data): Model {
                        try {
                            return app(ProjectPhotoService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('preview')
                    ->label(__('project_photo.actions.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (ProjectPhoto $record): string => FileLinks::photo($record, 'original', 'inline'))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label(__('project_photo.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (ProjectPhoto $record): string => FileLinks::photo($record, 'original', 'download')),
                Action::make('set_cover')
                    ->label(__('project_photo.actions.set_cover'))
                    ->icon(Heroicon::OutlinedStar)
                    ->visible(fn (ProjectPhoto $record): bool => ! $record->is_cover && Gate::allows('update', $record))
                    ->action(function (ProjectPhoto $record): void {
                        try {
                            app(ProjectPhotoService::class)->setCover($record);
                            DomainNotifications::success(__('project_photo.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ProjectPhoto $record): bool {
                        try {
                            return app(ProjectPhotoService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('project_photo.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }

    private function workstreamSelect(): Select
    {
        return Select::make('workstream_id')
            ->label(__('project_photo.fields.workstream'))
            ->relationship(
                'workstream',
                'id',
                modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
            )
            ->getOptionLabelFromRecordUsing(fn (ProjectWorkstream $record): string => $record->group->localizedName())
            ->searchable()
            ->preload()
            ->native(false);
    }
}
