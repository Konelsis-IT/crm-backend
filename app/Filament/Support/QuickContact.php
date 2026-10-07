<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\ContactRelationshipRole;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Query\Party\PartyQueries;
use App\Services\Party\ContactRelationshipService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Gorusulen kisi alanina "+" ile kisi ekleme (D-167, 6 Ekim 2026 kullanici
 * istegi: "Gorusme notu olustururken ... gorusulen kisi o ekranda + butonu ile
 * modal icinde eklenebilir olmalidir"). Filament'in kendi createOptionForm
 * penceresidir: kisi notun / planin tarafina ContactRelationshipService
 * uzerinden yazilir ve alanda secili gelir. Secenekler her seferinde sorgudan
 * okunur (PartyQueries::contactOptions), yeni kisi listede hemen gorunur.
 *
 * Kullanim: gorusme notu formu (MeetingNoteComponents: taraf, potansiyel is,
 * teklif sekmeleri ve "Notu duzenle") ve gorusme plani formu.
 */
final class QuickContact
{
    /**
     * Tarafin kisilerini listeleyen, "+" ile kisi ekleyen secim alani.
     *
     * @param  Closure  $party  gorusulen taraf (Party, kimlik ya da null); Filament enjeksiyonuyla degerlendirilir (Get $get kullanilabilir)
     */
    public static function select(string $name, string $label, Closure $party): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (Select $component): array => app(PartyQueries::class)->contactOptions(self::partyKey($component->evaluate($party))))
            ->searchable()
            ->native(false)
            ->createOptionForm(fn (): array => self::fields())
            ->createOptionUsing(fn (Select $component, array $data): int => self::create(self::partyKey($component->evaluate($party)), $data))
            ->createOptionAction(fn (Action $action, Select $component): Action => $action
                ->label(__('contact_relationship.quick.action'))
                ->tooltip(__('contact_relationship.quick.action'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalHeading(__('contact_relationship.quick.heading'))
                ->modalDescription(fn (): string => __('contact_relationship.quick.description', [
                    'party' => (string) (app(PartyQueries::class)->displayName((int) self::partyKey($component->evaluate($party))) ?? '-'),
                ]))
                ->modalSubmitActionLabel(__('contact_relationship.quick.submit'))
                ->modalWidth('4xl')
                ->visible(fn (): bool => self::enabled() && self::partyKey($component->evaluate($party)) !== null));
    }

    /** Hizli ekleme bu ortamda ve bu kisi icin acik mi (B27 kisi adi, Taraflar ozelligi, yetki). */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B27')
            && FeatureFlags::enabled(Feature::Parties)
            && Gate::allows('create', ContactRelationship::class);
    }

    /**
     * Pencere alanlari: ad, iliski rolu, departman, cep telefonu, e-posta.
     *
     * @return list<Grid>
     */
    private static function fields(): array
    {
        return [
            Grid::make(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                TextInput::make('contact_name')
                    ->label(__('contact_relationship.quick.name'))
                    ->required()
                    ->maxLength(200),
                Select::make('relationship_role')
                    ->label(__('contact_relationship.fields.relationship_role'))
                    ->options(ContactRelationshipRole::class)
                    ->default(ContactRelationshipRole::Other->value)
                    ->required()
                    ->native(false),
                TextInput::make('department_note')
                    ->label(__('contact_relationship.fields.department_note'))
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label(CommunicationChannelType::Mobile->getLabel())
                    ->tel()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label(CommunicationChannelType::Email->getLabel())
                    ->email()
                    ->maxLength(100),
            ])),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private static function create(?int $partyId, array $data): int
    {
        if ($partyId === null || ! self::enabled()) {
            throw new Halt;
        }

        try {
            $contact = app(ContactRelationshipService::class)->quickCreate($partyId, [
                ...$data,
                'relationship_role' => FormState::value($data['relationship_role'] ?? null),
            ]);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }

        DomainNotifications::success(__('contact_relationship.quick.created', ['name' => $contact->displayName()]));

        return (int) $contact->getKey();
    }

    private static function partyKey(mixed $party): ?int
    {
        $key = $party instanceof Party ? $party->getKey() : $party;

        return is_numeric($key) && (int) $key > 0 ? (int) $key : null;
    }
}
