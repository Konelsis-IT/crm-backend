<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Filament\Support\MeetingNoteComponents;
use App\Models\Acquisition\Proposal;
use App\Models\Party\Party;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklifin gorusme notlari (B41, D-137, 28 Eylul 2026 kullanici karari):
 * bu teklifin konusuldugu gorusmeler. Yeni not bu teklife ve potansiyel isine
 * baglanir; ayni isin diger teklifleri de secilebilir. Not potansiyel isin ve
 * musterinin sayfalarinda da gorunur.
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
            MeetingNoteComponents::CONTEXT_PROPOSAL,
            fn (): ?Party => $this->proposal()->businessCase?->primaryParty,
            fn (): ?int => (int) $this->proposal()->business_case_id,
        ));
    }

    public function table(Table $table): Table
    {
        return MeetingNoteComponents::table($table, MeetingNoteComponents::CONTEXT_PROPOSAL, fn (array $data): array => [
            ...$data,
            'business_case_id' => $this->proposal()->business_case_id,
            'party_id' => $this->proposal()->businessCase?->primary_party_id,
            'proposal_ids' => array_values(array_unique([(int) $this->proposal()->getKey(), ...array_map('intval', (array) ($data['proposal_ids'] ?? []))])),
        ]);
    }

    private function proposal(): Proposal
    {
        /** @var Proposal $proposal */
        $proposal = $this->getOwnerRecord();

        return $proposal;
    }
}
