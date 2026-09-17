<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\ContactRelationshipRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\BrandIcons;
use App\Filament\Support\ChannelActions;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\ContactRelationship;
use App\Services\Party\CommunicationPointService;
use App\Services\Party\ContactRelationshipService;
use App\Services\Platform\SchemaReadiness;
use App\Support\ContactLinks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contact_relationship.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contact_relationship.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('contact_name')
                            ->label(__('contact_relationship.fields.contact_name'))
                            ->helperText(__('contact_relationship.help.contact_name'))
                            ->maxLength(200)
                            ->visible(fn (): bool => SchemaReadiness::hasBatch('B27'))
                            ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B27'))
                            ->required(fn (): bool => SchemaReadiness::hasBatch('B27')),
                        Select::make('relationship_role')
                            ->label(__('contact_relationship.fields.relationship_role'))
                            ->options(ContactRelationshipRole::class)
                            ->default(ContactRelationshipRole::TechnicalContact->value)
                            ->required()
                            ->native(false),
                        TextInput::make('department_note')
                            ->label(__('contact_relationship.fields.department_note'))
                            ->maxLength(100),
                        // Network (B28): bu kisiyle nereden tanisildigi; B28
                        // uygulanana kadar gizli ve kaydedilmez.
                        TextInput::make('network_note')
                            ->label(__('contact_relationship.fields.network_note'))
                            ->helperText(__('contact_relationship.help.network_note'))
                            ->maxLength(255)
                            ->columnSpan(FieldGrid::WIDE)
                            ->visible(fn (): bool => SchemaReadiness::hasBatch('B28'))
                            ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B28')),
                        DatePicker::make('valid_from')
                            ->label(__('contact_relationship.fields.valid_from'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('valid_until')
                            ->label(__('contact_relationship.fields.valid_until'))
                            ->displayFormat('d.m.Y'),
                        Toggle::make('is_primary')
                            ->label(__('contact_relationship.fields.is_primary')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
            // Kisiye ait iletisim kanallari (D-94): sinirsiz satir; bos ise
            // kanal kurumun kendisinindir.
            Section::make(__('contact_relationship.sections.channels'))
                ->description(__('contact_relationship.help.channels'))
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B27'))
                ->components([
                    Repeater::make('communication_points')
                        ->hiddenLabel()
                        ->addActionLabel(__('contact_relationship.actions.add_channel'))
                        ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B27'))
                        ->afterStateHydrated(function (Repeater $component, ?ContactRelationship $record): void {
                            // Yeni kayitta varsayilan bos satir korunur; kayit varsa
                            // kisinin kanallari yuklenir.
                            if ($record === null) {
                                return;
                            }

                            $component->state($record->communicationPoints
                                ->map(fn (CommunicationPoint $point): array => [
                                    'channel_type' => $point->channel_type?->value,
                                    'value' => $point->value,
                                    'purpose' => $point->purpose,
                                    'is_primary' => (bool) $point->is_primary,
                                ])->all());
                            $component->hydrateItems();
                        })
                        ->table([
                            TableColumn::make(__('communication_point.fields.channel_type')),
                            TableColumn::make(__('communication_point.fields.value')),
                            TableColumn::make(__('communication_point.fields.purpose')),
                            TableColumn::make(__('communication_point.fields.is_primary')),
                        ])
                        ->schema([
                            Select::make('channel_type')
                                ->label(__('communication_point.fields.channel_type'))
                                ->options(CommunicationChannelType::class)
                                ->default(CommunicationChannelType::Mobile->value)
                                ->required()
                                ->native(false),
                            TextInput::make('value')
                                ->label(__('communication_point.fields.value'))
                                ->required()
                                ->maxLength(100),
                            TextInput::make('purpose')
                                ->label(__('communication_point.fields.purpose'))
                                ->maxLength(32),
                            Toggle::make('is_primary')
                                ->label(__('communication_point.fields.is_primary'))
                                ->inline(false),
                        ])
                        ->defaultItems(1)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contact_relationship.label'))
            ->heading(__('contact_relationship.relation.title'))
            ->recordTitleAttribute('relationship_role')
            ->columns([
                TextColumn::make('contact_name')
                    ->label(__('contact_relationship.fields.contact'))
                    ->state(fn (ContactRelationship $record): string => $record->displayName())
                    ->searchable(),
                TextColumn::make('relationship_role')
                    ->label(__('contact_relationship.fields.relationship_role'))
                    ->badge(),
                TextColumn::make('communicationPoints.value')
                    ->label(__('contact_relationship.fields.channels'))
                    ->state(fn (ContactRelationship $record): array => $record->communicationPoints
                        ->map(fn (CommunicationPoint $point): string => $point->channel_type?->getLabel().': '.$point->value)
                        ->all())
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('-')
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B27')),
                TextColumn::make('department_note')
                    ->label(__('contact_relationship.fields.department_note'))
                    ->placeholder('-'),
                TextColumn::make('network_note')
                    ->label(__('contact_relationship.fields.network_note'))
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B28')),
                IconColumn::make('is_primary')
                    ->label(__('contact_relationship.fields.is_primary'))
                    ->boolean(),
                TextColumn::make('valid_from')
                    ->label(__('contact_relationship.fields.valid_from'))
                    ->date('d.m.Y'),
                TextColumn::make('valid_until')
                    ->label(__('contact_relationship.fields.valid_until'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $channels = $this->pullChannels($data);
                        $data['organization_party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            $contact = app(ContactRelationshipService::class)->create($data);
                            $this->syncChannels($contact, $channels);

                            return $contact;
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                $this->channelActionGroup(),
                EditAction::make()
                    ->using(function (ContactRelationship $record, array $data): Model {
                        $channels = $this->pullChannels($data);

                        try {
                            $contact = app(ContactRelationshipService::class)->update($record, $data);
                            $this->syncChannels($contact, $channels);

                            return $contact;
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ContactRelationship $record): bool {
                        try {
                            return app(ContactRelationshipService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading(__('contact_relationship.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
    /**
     * Satir menusu "Iletisim": kisinin her kanali icin WhatsApp (telefonlarda)
     * ve kopyala. Kanal sayisi kayda gore degistigi icin yuvalar (0..N-1)
     * kurumun en kalabalik kisisine gore acilir; bos yuva gizlenir.
     */
    private function channelActionGroup(): ActionGroup
    {
        $owner = $this->getOwnerRecord();
        $slots = min(8, max(1, (int) $owner->contacts->map(fn (ContactRelationship $contact): int => $contact->communicationPoints->count())->max()));
        $actions = [];

        for ($i = 0; $i < $slots; $i++) {
            $point = fn (ContactRelationship $record): ?CommunicationPoint => $record->communicationPoints->values()->get($i);

            $actions[] = Action::make('whatsapp_'.$i)
                ->label(fn (ContactRelationship $record): string => __('personnel.actions.whatsapp_short').' · '.($point($record)?->value ?? ''))
                ->icon(BrandIcons::whatsapp())
                ->color('success')
                ->visible(fn (ContactRelationship $record): bool => ChannelActions::isPhone($point($record)?->channel_type) && ContactLinks::whatsapp($point($record)?->value) !== null)
                ->url(fn (ContactRelationship $record): string => (string) ContactLinks::whatsapp($point($record)?->value), shouldOpenInNewTab: true);

            $actions[] = Action::make('copy_'.$i)
                ->label(fn (ContactRelationship $record): string => __('communication_point.actions.copy').' · '.($point($record)?->value ?? ''))
                ->icon(Heroicon::OutlinedClipboardDocument)
                ->color('gray')
                ->visible(fn (ContactRelationship $record): bool => $point($record) !== null)
                ->livewireClickHandlerEnabled(false)
                ->alpineClickHandler(fn (ContactRelationship $record): string => 'window.navigator.clipboard.writeText('.Js::from((string) $point($record)?->value).'); $tooltip('.Js::from(__('communication_point.actions.copied')).', { theme: $store.theme, timeout: 1500 })');
        }

        return ActionGroup::make($actions)
            ->label(__('communication_point.actions.group'))
            ->tooltip(__('communication_point.actions.group'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('primary')
            ->visible(fn (ContactRelationship $record): bool => SchemaReadiness::hasBatch('B27') && $record->communicationPoints->isNotEmpty());
    }

    /**
     * Formdan gelen kanal satirlarini ayirir (D-94).
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function pullChannels(array &$data): array
    {
        $rows = (array) ($data['communication_points'] ?? []);
        unset($data['communication_points']);

        return array_values(array_filter(
            $rows,
            static fn ($row): bool => is_array($row) && filled($row['value'] ?? null),
        ));
    }

    /**
     * Kisinin kanallarini formdaki listeyle esitler: silinen satir silinir,
     * kalanlar yeniden yazilir. Kanal hem kisiye hem kuruma baglanir.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncChannels(ContactRelationship $contact, array $rows): void
    {
        if (! SchemaReadiness::hasBatch('B27')) {
            return;
        }

        $service = app(CommunicationPointService::class);

        foreach ($contact->communicationPoints()->get() as $existing) {
            $service->delete($existing);
        }

        foreach ($rows as $row) {
            $service->create([
                'party_id' => $contact->organization_party_id,
                'contact_relationship_id' => $contact->getKey(),
                'channel_type' => $row['channel_type'] ?? null,
                'value' => $row['value'] ?? null,
                'purpose' => $row['purpose'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ]);
        }

        $contact->unsetRelation('communicationPoints');
    }
}
