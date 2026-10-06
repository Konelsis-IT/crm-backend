<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Gorusme notu. D-156 (5 Ekim 2026 kullanici talebi: "Gorusme notlarini
 * kaydettikten sonra duzenleme silme islemi yapilmiyor"): notu yazan ya da
 * gorusmeyi yapan personel kendi notunu duzenler ve arsive alir. Silme yoktur;
 * arsiv vardir (archive).
 */
final class PartyMeetingNotePolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update') || $this->isOwn($personnel, $record);
    }

    /** Arsive alma ve arsivden cikarma (D-156): duzenleyebilen yapar. */
    public function archive(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return $this->update($personnel, $record);
    }

    public function delete(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'delete');
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, PartyMeetingNote $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    /** Notu yazan ya da gorusmeyi yapan (aktif) personel. */
    private function isOwn(Personnel $personnel, PartyMeetingNote $record): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        $id = (int) $personnel->getKey();

        return (int) $record->personnel_id === $id || (int) $record->getAttribute('created_by_personnel_id') === $id;
    }
}
