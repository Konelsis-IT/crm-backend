<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Filament\Support\MeetingNoteComponents;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\Party;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Potansiyel isin gorusme notlari (B41, D-137, 28 Eylul 2026 kullanici
 * karari): bu is ve teklifleri hakkindaki gorusmeler; teklif sutunu notun
 * hangi tekliflerle ilgili oldugunu gosterir. Not musterinin taraf kartinda
 * da gorunur.
 */
class MeetingNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'meetingNotes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('party_meeting_note.relation.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return MeetingNoteComponents::dealLinksEnabled() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(MeetingNoteComponents::form(
            MeetingNoteComponents::CONTEXT_CASE,
            fn (): ?Party => $this->case()->primaryParty,
            fn (): ?int => (int) $this->case()->getKey(),
        ));
    }

    public function table(Table $table): Table
    {
        return MeetingNoteComponents::table($table, MeetingNoteComponents::CONTEXT_CASE, fn (array $data): array => [
            ...$data,
            'business_case_id' => $this->case()->getKey(),
            'party_id' => $this->case()->primary_party_id,
        ]);
    }

    private function case(): BusinessCase
    {
        /** @var BusinessCase $case */
        $case = $this->getOwnerRecord();

        return $case;
    }
}
