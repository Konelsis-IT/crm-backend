<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Parties\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\MeetingNotesRelationManager;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Query\Party\ContactSearchQueries;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Genel arama (D-167, 6 Ekim 2026 kullanici istegi: "Genel aramada gorusulen
 * kisi, taraf detayindaki kisi bilgisi vs. genel aramada cikmadi, onlar da
 * ciksin"). Filament'in kendi arama saglayicisinin sonuclarina "Kisiler"
 * kategorisi eklenir: taraflarin iletisim kisileri ve gorusme notlarindaki
 * gorusulen kisiler (ayni satirlar; ContactSearchQueries). Kategori
 * "Taraflar"in hemen arkasinda durur. Sonuc kisinin firmasinin kartini
 * "Iletisim ve kisiler" sekmesinde acar; kisiyle gorusme varsa "Gorusme
 * notlari", kisinin kendi taraf kaydi varsa "Kisi karti" baglantisi da cikar.
 *
 * Yetki: Taraflar ekranina erisim (ozellik anahtari dahil), kisi listesini
 * gorme izni ve firmanin kendi kayit yetkisi.
 */
final class KonelsisGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $builder = parent::getResults($query);
        $contacts = $this->contactResults($query);

        if ($builder === null || $contacts === []) {
            return $builder;
        }

        $label = __('contact_relationship.search.category');
        $after = PartyResource::getPluralModelLabel();
        $results = new GlobalSearchResults;
        $placed = false;

        foreach ($builder->getCategories() as $name => $items) {
            $results->category((string) $name, $items);

            if ((string) $name === $after) {
                $results->category($label, $contacts);
                $placed = true;
            }
        }

        if (! $placed) {
            $results->category($label, $contacts);
        }

        return $results;
    }

    /**
     * @return list<GlobalSearchResult>
     */
    private function contactResults(string $query): array
    {
        if (trim($query) === '' || ! PartyResource::canAccess() || ! Gate::allows('viewAny', ContactRelationship::class)) {
            return [];
        }

        $relations = PartyResource::getRelations();
        $contactsTab = array_search(ContactsRelationManager::class, $relations, true);
        $notesTab = array_search(MeetingNotesRelationManager::class, $relations, true);
        $results = [];

        foreach (app(ContactSearchQueries::class)->search($query) as $contact) {
            $party = $contact->organization;

            if (! $party instanceof Party || ! PartyResource::canView($party)) {
                continue;
            }

            $lastMeeting = $this->date($contact->getAttribute('last_noted_on'));
            $personUrl = $this->personUrl($contact);

            $results[] = new GlobalSearchResult(
                title: $contact->displayName(),
                url: $this->partyUrl($party, $contactsTab),
                details: array_filter([
                    __('contact_relationship.search.party') => (string) $party->display_name,
                    __('contact_relationship.search.role') => $this->role($contact),
                    __('contact_relationship.search.channel') => $this->channels($contact),
                    __('contact_relationship.search.last_meeting') => $lastMeeting,
                ], static fn (?string $value): bool => filled($value)),
                actions: array_values(array_filter([
                    $lastMeeting !== null && $notesTab !== false
                        ? Action::make('meeting_notes')
                            ->label(__('contact_relationship.search.open_notes'))
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->url($this->partyUrl($party, $notesTab))
                        : null,
                    $personUrl !== null
                        ? Action::make('contact_card')
                            ->label(__('contact_relationship.search.open_contact'))
                            ->icon(RecordLinks::PERSONNEL_ICON)
                            ->url($personUrl)
                        : null,
                ])),
            );
        }

        return $results;
    }

    /** Firma karti; verilen sekme (relation manager anahtari) acik gelir. */
    private function partyUrl(Party $party, int|string|false $tab): string
    {
        return PartyResource::getUrl('view', [
            'record' => $party,
            ...($tab !== false ? ['relation' => (string) $tab] : []),
        ]);
    }

    /** Kisinin kendi taraf kaydi (varsa ve gorulebiliyorsa). */
    private function personUrl(ContactRelationship $contact): ?string
    {
        return $contact->contact instanceof Party ? RecordLinks::detailUrl($contact->contact) : null;
    }

    private function role(ContactRelationship $contact): ?string
    {
        $parts = array_filter([
            $contact->relationship_role?->getLabel(),
            filled($contact->department_note) ? (string) $contact->department_note : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Kisinin ilk iki iletisim degeri (telefon, e-posta...). */
    private function channels(ContactRelationship $contact): ?string
    {
        $values = $contact->communicationPoints
            ->take(2)
            ->map(fn (CommunicationPoint $point): string => (string) $point->value)
            ->filter()
            ->all();

        return $values === [] ? null : implode(' · ', $values);
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d.m.Y');
        } catch (Throwable) {
            return null;
        }
    }
}
