<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\MeetingChannel;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\ContactRelationship;
use App\Models\Party\PartyMeetingNote;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Party\PartyMeetingNoteService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Taraf gorusme notlari (B28, 16 Eylul 2026 kullanici karari): ziyaret,
 * telefon, e-posta gibi her temasin kisa kaydi; gorusulen kisi, gorusen
 * personel ve sonraki adim tarihiyle birlikte tutulur. Yazma islemleri
 * PartyMeetingNoteService uzerinden gecer.
 */
class MeetingNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'meetingNotes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('party_meeting_note.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('party_meeting_note.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        DatePicker::make('noted_on')
                            ->label(__('party_meeting_note.fields.noted_on'))
                            ->displayFormat('d.m.Y')
                            ->default(fn (): string => now()->toDateString())
                            ->required(),
                        Select::make('channel')
                            ->label(__('party_meeting_note.fields.channel'))
                            ->options(MeetingChannel::class)
                            ->default(MeetingChannel::Phone->value)
                            ->required()
                            ->native(false),
                        Select::make('contact_relationship_id')
                            ->label(__('party_meeting_note.fields.contact'))
                            ->options(fn (): array => $this->getOwnerRecord()->contacts
                                ->mapWithKeys(fn (ContactRelationship $contact): array => [$contact->getKey() => $contact->displayName()])
                                ->all())
                            ->searchable()
                            ->native(false),
                        Select::make('personnel_id')
                            ->label(__('party_meeting_note.fields.personnel'))
                            ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->native(false),
                        TextInput::make('subject')
                            ->label(__('party_meeting_note.fields.subject'))
                            ->maxLength(200)
                            ->columnSpan(FieldGrid::WIDE),
                        Textarea::make('note')
                            ->label(__('party_meeting_note.fields.note'))
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        DatePicker::make('next_action_on')
                            ->label(__('party_meeting_note.fields.next_action_on'))
                            ->displayFormat('d.m.Y'),
                        TextInput::make('next_action')
                            ->label(__('party_meeting_note.fields.next_action'))
                            ->maxLength(255)
                            ->columnSpan(FieldGrid::WIDE),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('party_meeting_note.label'))
            ->heading(__('party_meeting_note.relation.title'))
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('noted_on')
                    ->label(__('party_meeting_note.fields.noted_on'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label(__('party_meeting_note.fields.channel'))
                    ->badge(),
                TextColumn::make('contact.contact_name')
                    ->label(__('party_meeting_note.fields.contact'))
                    ->state(fn (PartyMeetingNote $record): string => $record->contact?->displayName() ?? '-'),
                TextColumn::make('personnel.full_name')
                    ->label(__('party_meeting_note.fields.personnel'))
                    ->placeholder('-'),
                TextColumn::make('subject')
                    ->label(__('party_meeting_note.fields.subject'))
                    ->placeholder('-'),
                TextColumn::make('note')
                    ->label(__('party_meeting_note.fields.note'))
                    ->limit(80)
                    ->wrap()
                    ->tooltip(fn (PartyMeetingNote $record): string => $record->note),
                TextColumn::make('next_action_on')
                    ->label(__('party_meeting_note.fields.next_action_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PartyMeetingNoteService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PartyMeetingNote $record, array $data): Model {
                        try {
                            return app(PartyMeetingNoteService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (PartyMeetingNote $record): bool {
                        try {
                            return app(PartyMeetingNoteService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('noted_on', 'desc')
            ->emptyStateHeading(__('party_meeting_note.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }
}
