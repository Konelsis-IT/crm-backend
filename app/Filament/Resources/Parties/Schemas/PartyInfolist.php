<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Schemas;

use App\Filament\Support\CardGallery;
use App\Models\Party\Party;
use App\Services\Platform\SchemaReadiness;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Taraf ayrinti sayfasi (16 Eylul 2026, kullanici istegi: "personel
 * detayindaki gibi kart yapisi"). Ustte yatay taraf karti (ad, taraf no,
 * tur; taraf tipi / ziyaret onceligi / arsiv rozetleri; adres ve kuruma ait
 * butun iletisim bilgileri tiklanabilir satirlar halinde; network ve
 * varsayilan yetkili kisi; altta WhatsApp ve duzenle) ve sagda 1/4
 * genisliginde ozet kutusu (ziyaret onceligi, gorusme notu sayisi, son
 * gorusme, kayit tarihi; arsivliyse arsiv bilgisi). Iliski sekmeleri
 * (taraf tipi, adresler, kisiler, gorusme notlari...) sayfanin altinda durur.
 */
final class PartyInfolist
{
    /**
     * $resource: karttaki "Duzenle" hangi kaynagin sayfasini acar (Dernekler
     * ayni karti kullanir).
     *
     * @param  class-string<\Filament\Resources\Resource>|null  $resource
     */
    public static function configure(Schema $schema, ?string $resource = null): Schema
    {
        $record = $schema->getRecord();

        if (! $record instanceof Party) {
            return $schema->components([]);
        }

        $record->loadMissing(['country', 'roles', 'addresses', 'ownCommunicationPoints', 'contacts.contact', 'archivedBy']);

        if (SchemaReadiness::hasBatch('B28')) {
            $record->loadMissing('meetingNotes');
        }

        if (SchemaReadiness::hasBatch('B33')) {
            $record->loadMissing(['activityAreas.activityArea', 'activityAreas.subActivityArea']);
        }

        $archived = fn (Party $party): bool => $party->archived_at !== null;
        $meetings = fn (): bool => SchemaReadiness::hasBatch('B28');
        $activities = fn (): bool => SchemaReadiness::hasBatch('B33');

        return $schema->columns(1)->components([
            // Kart ve ozet kendi boylarini korur (esnetme yok).
            Grid::make(['default' => 1, 'lg' => 4])->extraAttributes(['class' => 'kc-grid-top'])->components([
                app(CardGallery::class)
                    ->partyDetailCard($record, $resource !== null ? $resource::getUrl('edit', ['record' => $record]) : null)
                    ->columnSpan(['default' => 1, 'lg' => 3]),
                Section::make(__('party.sections.side'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->components([
                        TextEntry::make('visit_priority')
                            ->label(__('party.fields.visit_priority'))
                            ->badge()
                            ->placeholder('-')
                            ->visible($meetings),
                        // Rakip firma (B33); koken ve faaliyet alanlari soldaki kartta.
                        TextEntry::make('is_competitor')
                            ->label(__('party.fields.is_competitor'))
                            ->state(fn (Party $party): string => $party->is_competitor ? __('party.values.competitor') : __('party.values.not_competitor'))
                            ->badge()
                            ->color(fn (Party $party): string => $party->is_competitor ? 'warning' : 'gray')
                            ->visible($activities),
                        TextEntry::make('meeting_count')
                            ->label(__('party.fields.meeting_count'))
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->state(fn (Party $party): int => $party->meetingNotes->count())
                            ->visible($meetings),
                        TextEntry::make('last_meeting')
                            ->label(__('party.fields.last_meeting'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->state(fn (Party $party): ?string => $party->meetingNotes->max('noted_on')?->format('d.m.Y'))
                            ->placeholder('-')
                            ->visible($meetings),
                        TextEntry::make('created_at')
                            ->label(__('party.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime('d.m.Y H:i'),
                        // Arsiv bilgisi (S3, D-99): yalniz arsivli kayitta.
                        TextEntry::make('archived_at')
                            ->label(__('party.fields.archived_at'))
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->iconColor('danger')
                            ->dateTime('d.m.Y H:i')
                            ->visible($archived),
                        TextEntry::make('archivedBy.full_name')
                            ->label(__('party.fields.archived_by'))
                            ->icon(Heroicon::OutlinedUser)
                            ->placeholder('-')
                            ->visible($archived),
                        TextEntry::make('archive_reason')
                            ->label(__('party.fields.archive_reason'))
                            ->placeholder('-')
                            ->visible($archived),
                    ]),
            ]),
        ]);
    }
}
