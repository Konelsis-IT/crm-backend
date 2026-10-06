<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Party\MeetingChannel;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Models\Party\Party;
use App\Models\Party\PartyMeetingNote;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Query\Acquisition\ProposalQueries;
use App\Query\Party\MeetingNoteQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component as LivewireComponent;

/**
 * Gorusme notu formu ve tablosu (B28, D-98; B41, D-137): taraf kartindaki,
 * potansiyel is ve teklif sayfalarindaki "Gorusme notlari" sekmeleri ayni
 * alanlari ve sutunlari kullanir. Yazmalar PartyMeetingNoteService'ten gecer.
 *
 * Baglam:
 *  - party    : not tarafa yazilir; istenirse tarafin potansiyel isi ve o isin
 *               teklifleri secilir.
 *  - case     : not potansiyel ise (ve musterisine) yazilir; isin teklifleri secilir.
 *  - proposal : not teklife (ve potansiyel isine) yazilir; ayni isin diger
 *               teklifleri de eklenebilir.
 */
final class MeetingNoteComponents
{
    public const CONTEXT_PARTY = 'party';

    public const CONTEXT_CASE = 'case';

    public const CONTEXT_PROPOSAL = 'proposal';

    /** Potansiyel is / teklif baglantisi kullanilabilir mi (B41 + ozellik anahtari). */
    public static function dealLinksEnabled(): bool
    {
        return SchemaReadiness::hasBatch('B41') && FeatureFlags::enabled(Feature::DealMeetingNotes);
    }

    /** Arsiv (B44, D-156) bu ortamda var mi. */
    public static function archiveEnabled(): bool
    {
        return SchemaReadiness::hasBatch('B44');
    }

    /**
     * Arsive al (D-156: "Projede silme islemi yok dedik ama arsive alinabilmeli"):
     * not ve ondan dogan gorusme plani satirlari birlikte arsivlenir (yalniz
     * archived_at). Tabloda, gorusme plani sayfasinda ayni eylem.
     *
     * @param  (Closure(Model): ?PartyMeetingNote)|null  $note  kayittan notu bulur (gorusme plani sayfasi)
     */
    public static function archiveAction(string $name = 'archive_note', ?Closure $note = null): Action
    {
        $resolve = $note ?? static fn (Model $record): ?PartyMeetingNote => $record instanceof PartyMeetingNote ? $record : null;

        return Action::make($name)
            ->label(__('party_meeting_note.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('party_meeting_note.actions.archive'))
            ->modalSubmitActionLabel(__('party_meeting_note.actions.archive'))
            ->modalDescription(__('party_meeting_note.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->visible(function (Model $record) use ($resolve): bool {
                $target = $resolve($record);

                return self::archiveEnabled() && $target instanceof PartyMeetingNote && ! $target->isArchived() && Gate::allows('archive', $target);
            })
            ->action(function (Model $record, LivewireComponent $livewire) use ($resolve): void {
                $target = $resolve($record);

                if (! $target instanceof PartyMeetingNote) {
                    return;
                }

                try {
                    app(PartyMeetingNoteService::class)->archive($target);
                    DomainNotifications::success(__('party_meeting_note.messages.archived'));
                    self::refreshPage($livewire);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Arsivden cikar: not ve onunla birlikte arsivlenen plan satirlari geri doner.
     *
     * @param  (Closure(Model): ?PartyMeetingNote)|null  $note
     */
    public static function restoreAction(string $name = 'restore_note', ?Closure $note = null): Action
    {
        $resolve = $note ?? static fn (Model $record): ?PartyMeetingNote => $record instanceof PartyMeetingNote ? $record : null;

        return Action::make($name)
            ->label(__('party_meeting_note.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('party_meeting_note.actions.restore'))
            ->modalDescription(__('party_meeting_note.help.restore'))
            ->visible(function (Model $record) use ($resolve): bool {
                $target = $resolve($record);

                return self::archiveEnabled() && $target instanceof PartyMeetingNote && $target->isArchived() && Gate::allows('archive', $target);
            })
            ->action(function (Model $record, LivewireComponent $livewire) use ($resolve): void {
                $target = $resolve($record);

                if (! $target instanceof PartyMeetingNote) {
                    return;
                }

                try {
                    app(PartyMeetingNoteService::class)->restore($target);
                    DomainNotifications::success(__('party_meeting_note.messages.restored'));
                    self::refreshPage($livewire);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Notu duzenle (D-156): gorusme plani sayfasinda sonucu girilmis gorusmenin
     * notu, Gorusme notlari sekmesindeki formla ayni pencerede duzenlenir.
     *
     * @param  Closure(Model): ?PartyMeetingNote  $note
     */
    public static function editNoteAction(Closure $note, string $name = 'edit_note'): Action
    {
        $links = self::dealLinksEnabled();

        return Action::make($name)
            ->label(__('party_meeting_note.actions.edit'))
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->color(ActionColors::EDIT)
            ->modalHeading(__('party_meeting_note.actions.edit'))
            ->modalSubmitActionLabel(__('app.actions.save'))
            ->modalWidth('6xl')
            ->visible(function (Model $record) use ($note): bool {
                $target = $note($record);

                return $target instanceof PartyMeetingNote && ! $target->isArchived() && Gate::allows('update', $target);
            })
            ->fillForm(function (Model $record) use ($note, $links): array {
                $target = $note($record);

                if (! $target instanceof PartyMeetingNote) {
                    return [];
                }

                return [
                    ...$target->attributesToArray(),
                    'noted_on' => $target->noted_on?->toDateString(),
                    'next_action_on' => $target->next_action_on?->toDateString(),
                    ...($links ? ['proposal_ids' => $target->proposals->modelKeys()] : []),
                ];
            })
            ->schema(function (Schema $schema, Model $record) use ($note): Schema {
                $target = $note($record);

                return $schema->columns(1)->components(self::form(
                    self::CONTEXT_PARTY,
                    fn (): ?Party => $target?->party,
                    fn (Get $get): ?int => filled($get('business_case_id')) ? (int) $get('business_case_id') : null,
                ));
            })
            ->action(function (Model $record, array $data, LivewireComponent $livewire) use ($note): void {
                $target = $note($record);

                if (! $target instanceof PartyMeetingNote) {
                    return;
                }

                try {
                    app(PartyMeetingNoteService::class)->update($target, $data);
                    DomainNotifications::success(__('party_meeting_note.messages.updated'));
                    self::refreshPage($livewire);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Ayrinti sayfasinda kart yeniden yuklenir; tabloda satir kendiliginden yenilenir. */
    private static function refreshPage(LivewireComponent $livewire): void
    {
        if ($livewire instanceof ViewRecord) {
            $livewire->redirect($livewire::getResource()::getUrl('view', ['record' => $livewire->getRecord()]), navigate: true);
        }
    }

    /**
     * @param  Closure(): ?Party  $party  gorusulen taraf (kisi secimi ve potansiyel is listesi)
     * @param  Closure(Get): ?int  $caseId  teklif secimi icin potansiyel is
     * @return list<Component>
     */
    public static function form(string $context, Closure $party, Closure $caseId): array
    {
        $links = self::dealLinksEnabled();

        return [
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
                        ->options(fn (): array => ($party()?->contacts ?? collect())
                            ->mapWithKeys(fn ($contact): array => [$contact->getKey() => $contact->displayName()])
                            ->all())
                        ->searchable()
                        ->native(false),
                    Select::make('personnel_id')
                        ->label(__('party_meeting_note.fields.personnel'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->default(fn () => auth()->id())
                        ->searchable()
                        ->native(false),
                    ...($links && $context === self::CONTEXT_PARTY ? [
                        Select::make('business_case_id')
                            ->label(__('party_meeting_note.fields.business_case'))
                            ->helperText(__('party_meeting_note.help.business_case'))
                            ->options(fn (): array => ($owner = $party()) !== null ? app(BusinessCaseQueries::class)->optionsForParty((int) $owner->getKey()) : [])
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('proposal_ids', []))
                            ->native(false)
                            ->columnSpan(FieldGrid::WIDE),
                    ] : []),
                    ...($links ? [
                        Select::make('proposal_ids')
                            ->label(__('party_meeting_note.fields.proposals'))
                            ->helperText(__('party_meeting_note.help.proposals'))
                            ->multiple()
                            ->options(fn (Get $get): array => app(ProposalQueries::class)->optionsForCase($caseId($get)))
                            ->visible(fn (Get $get): bool => $caseId($get) !== null)
                            ->native(false)
                            ->columnSpan(FieldGrid::WIDE),
                    ] : []),
                    TextInput::make('subject')
                        ->label(__('party_meeting_note.fields.subject'))
                        ->maxLength(200)
                        ->columnSpan(FieldGrid::WIDE),
                    Textarea::make('note')
                        ->label(__('party_meeting_note.fields.note'))
                        ->required()
                        ->rows(4)
                        ->columnSpan(FieldGrid::LONG),
                    // Sonraki adim hatirlatmasi (B34, D-109): tarih verilirse adim Gorusme
                    // planina duser, 1 gun once ve gunun sabahi zil bildirimi gider.
                    DatePicker::make('next_action_on')
                        ->label(__('party_meeting_note.fields.next_action_on'))
                        ->displayFormat('d.m.Y')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: __('party_meeting_note.help.next_action_reminder'))
                        ->hintColor('primary'),
                    TextInput::make('next_action')
                        ->label(__('party_meeting_note.fields.next_action'))
                        ->maxLength(255)
                        ->columnSpan(FieldGrid::WIDE),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ];
    }

    /**
     * Tablo: taraf kartinda potansiyel is ve teklif sutunlari, potansiyel iste
     * teklif sutunu, teklifte ikisi de gizli (zaten o kayittasiniz).
     *
     * @param  Closure(array<string, mixed>): array<string, mixed>  $beforeCreate  baglam verisi (taraf, potansiyel is, teklif)
     */
    public static function table(Table $table, string $context, Closure $beforeCreate): Table
    {
        $links = self::dealLinksEnabled();

        return $table
            ->modelLabel(__('party_meeting_note.label'))
            ->heading(__('party_meeting_note.relation.title'))
            ->recordTitleAttribute('subject')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'contact', 'personnel', ...($links ? ['businessCase.codes', 'proposals'] : []),
            ]))
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
                TextColumn::make('businessCase.title')
                    ->label(__('party_meeting_note.fields.business_case'))
                    ->state(fn (PartyMeetingNote $record): ?string => $record->businessCase !== null
                        ? trim(($record->businessCase->caseCode()?->formatted_code ?? '').' · '.$record->businessCase->title, ' ·')
                        : null)
                    ->limit(40)
                    ->placeholder('-')
                    ->visible($links && $context === self::CONTEXT_PARTY),
                TextColumn::make('proposals.proposal_no')
                    ->label(__('party_meeting_note.fields.proposals'))
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->visible($links && $context !== self::CONTEXT_PROPOSAL),
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
                // Arsiv bilgisi (B44): "Arsivlenenler" suzgecinde dolu gelir.
                TextColumn::make('archived_at')
                    ->label(__('party_meeting_note.fields.archived_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => self::archiveEnabled()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data) use ($beforeCreate): Model {
                        try {
                            return app(PartyMeetingNoteService::class)->create($beforeCreate($data));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->filters([
                // Arsiv (B44, D-156): varsayilan yalniz aktif notlar.
                SelectFilter::make('archive')
                    ->label(__('party_meeting_note.filters.archive'))
                    ->options([
                        'active' => __('party_meeting_note.filters.archive_active'),
                        'archived' => __('party_meeting_note.filters.archive_archived'),
                        'all' => __('party_meeting_note.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->visible(fn (): bool => self::archiveEnabled())
                    ->query(fn (Builder $query, array $data): Builder => app(MeetingNoteQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (PartyMeetingNote $record): bool => ! $record->isArchived())
                    ->mutateRecordDataUsing(function (array $data, PartyMeetingNote $record) use ($links): array {
                        if ($links) {
                            $data['proposal_ids'] = $record->proposals->modelKeys();
                        }

                        return $data;
                    })
                    ->using(function (PartyMeetingNote $record, array $data): Model {
                        try {
                            return app(PartyMeetingNoteService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                // Silme yok, arsiv var (D-156).
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('noted_on', 'desc')
            ->emptyStateHeading(__('party_meeting_note.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }
}
