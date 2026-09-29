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
use App\Query\Personnel\PersonnelQueries;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                        ->columnSpanFull(),
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
            ->recordActions([
                EditAction::make()
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
