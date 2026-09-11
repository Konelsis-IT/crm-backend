<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Schemas;

use App\Filament\Support\CardGallery;
use App\Models\Personnel\Personnel;
use App\Services\Platform\SchemaReadiness;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Personel ayrinti sayfasi (11 Eylul 2026, kullanici istegi). Ustte yatay
 * personel karti (kimlik, iletisim, TC kimlik no, gorev; departman /
 * yetkinlik / rol rozetleri satir satir; altta WhatsApp, sohbet, duzenle)
 * ve sagda 1/4 genisliginde amir, ise giris, son giris kutusu. Eski
 * "Kimlik ve iletisim", "Gorev bilgileri", "Yetkinlikler" ve "Hesap ve
 * giris" bolumleri kaldirildi; bilgiler karta ve yan kutuya tasindi.
 * Iliski sekmeleri (hareketler, sertifikalar...) sayfanin altinda durur.
 */
final class PersonnelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();

        if (! $record instanceof Personnel) {
            return $schema->components([]);
        }

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 4])->components([
                app(CardGallery::class)
                    ->personnelDetailCard($record)
                    ->columnSpan(['default' => 1, 'lg' => 3]),
                Section::make(__('personnel.sections.side'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->components([
                        TextEntry::make('current_manager')
                            ->label(__('personnel.fields.direct_manager'))
                            ->icon(Heroicon::OutlinedUser)
                            ->state(fn (Personnel $record): ?string => $record->currentManager()?->full_name)
                            ->placeholder('-')
                            ->visible(fn (): bool => SchemaReadiness::hasBatch('B03')),
                        TextEntry::make('hired_on')
                            ->label(__('personnel.fields.hired_on'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->date('d.m.Y')
                            ->placeholder('-'),
                        TextEntry::make('last_login_at')
                            ->label(__('personnel.fields.last_login_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime('d.m.Y H:i')
                            ->placeholder(__('personnel.messages.never_logged_in')),
                    ]),
            ]),
        ]);
    }
}
