<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Filament\Support\MeetingNoteComponents;
use App\Models\Party\Party;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Taraf gorusme notlari (B28, 16 Eylul 2026 kullanici karari): ziyaret,
 * telefon, e-posta gibi her temasin kisa kaydi; gorusulen kisi, gorusen
 * personel ve sonraki adim tarihiyle birlikte tutulur. B41 (D-137): not
 * tarafin bir potansiyel isine ve o isin tekliflerine baglanabilir; form ve
 * tablo MeetingNoteComponents'ten gelir, yazmalar PartyMeetingNoteService'ten.
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
        return $schema->columns(1)->components(MeetingNoteComponents::form(
            MeetingNoteComponents::CONTEXT_PARTY,
            fn (): ?Party => $this->getOwnerRecord() instanceof Party ? $this->getOwnerRecord() : null,
            fn (Get $get): ?int => filled($get('business_case_id')) ? (int) $get('business_case_id') : null,
        ));
    }

    public function table(Table $table): Table
    {
        return MeetingNoteComponents::table($table, MeetingNoteComponents::CONTEXT_PARTY, fn (array $data): array => [
            ...$data,
            'party_id' => $this->getOwnerRecord()->getKey(),
        ]);
    }
}
