<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Chat\Conversation;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Sohbet (D-83): her aktif personel sohbet baslatabilir; bir sohbeti yalniz
 * uyeleri gorur. Silme yok — kisi kendi tarafinda temizler (history_visible_from).
 */
final class ConversationPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, Conversation $record): bool
    {
        return $personnel->isActive() && $this->isMember($personnel, $record);
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function update(Personnel $personnel, Conversation $record): bool
    {
        return $personnel->isActive() && $this->isMember($personnel, $record);
    }

    public function delete(Personnel $personnel, Conversation $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Conversation $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Conversation $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Conversation $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function isMember(Personnel $personnel, Conversation $record): bool
    {
        return $record->memberships()->where('personnel_id', $personnel->getKey())->exists();
    }
}
