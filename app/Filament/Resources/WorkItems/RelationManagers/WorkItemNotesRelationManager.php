<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\RelationManagers;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\RowDetail;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Report\WorkItemService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Isin notlari (D-219, B57; kullanici 16 Ekim 2026: "varsa da iş detaylarında
 * not olarak eklenebilmelidir. Bir iş için birden fazla notta eklenebilir.").
 * Tarihli ve kaynakli (mail, WhatsApp, saha raporu...); en yenisi ustte. Not
 * duzenlenmez, yenisi yazilir; isin hareket gecmisine de islenir.
 */
class WorkItemNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return FeatureFlags::enabled(Feature::WorkItemsExtended)
            && WorkItemQueries::extended()
            && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_item.notes.title');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord instanceof WorkItem ? $ownerRecord->notes()->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('work_item.notes.title'))
            ->description(__('work_item.notes.help'))
            ->columns([
                TextColumn::make('noted_at')
                    ->label(__('work_item.notes.noted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->width('9rem'),
                TextColumn::make('body')
                    ->label(__('work_item.notes.body'))
                    ->wrap(),
                TextColumn::make('source')
                    ->label(__('work_item.notes.source'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('–'),
                TextColumn::make('author.full_name')
                    ->label(__('work_item.notes.author'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->placeholder(__('work_item.notes.imported')),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('author:id,full_name'))
            ->headerActions([
                Action::make('add_note')
                    ->label(__('work_item.notes.add'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('success')
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->modalWidth('2xl')
                    ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                        Textarea::make('body')
                            ->label(__('work_item.notes.body'))
                            ->rows(5)
                            ->maxLength(5000)
                            ->required()
                            ->columnSpan(FieldGrid::MODAL_LONG),
                        TextInput::make('source')
                            ->label(__('work_item.notes.source'))
                            ->placeholder(__('work_item.notes.source_placeholder'))
                            ->maxLength(40),
                        DatePicker::make('noted_on')
                            ->label(__('work_item.notes.noted_on'))
                            ->displayFormat('d.m.Y')
                            ->default(fn (): string => WorkItemQueries::today()->toDateString())
                            ->maxDate(fn (): string => WorkItemQueries::today()->toDateString()),
                    ])))
                    ->action(function (array $data): void {
                        $day = filled($data['noted_on'] ?? null) ? (string) $data['noted_on'] : null;

                        try {
                            app(WorkItemService::class)->addNote(
                                $this->getOwnerRecord(),
                                (string) ($data['body'] ?? ''),
                                filled($data['source'] ?? null) ? (string) $data['source'] : null,
                                // Bugunun notu su anin saatiyle; gecmis gunun notu o gunun basiyla.
                                $day !== null && $day !== WorkItemQueries::today()->toDateString() ? $day : null,
                            );
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }

                        DomainNotifications::success(__('work_item.notes.added'));
                    }),
            ])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
            ])
            ->toolbarActions([])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('noted_at')->orderByDesc('id'))
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('work_item.notes.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftEllipsis);
    }
}
