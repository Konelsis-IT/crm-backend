<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Chat\Message;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Mesaj (D-83): sohbetin uyeleri gorur ve yazar; silme tek taraflidir
 * (MessageService::hideForMe), kayit silinmez.
 */
final class MessagePolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, Message $record): bool
    {
        return $personnel->isActive() && $this->isMember($personnel, $record);
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function update(Personnel $personnel, Message $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, Message $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Message $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Message $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Message $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function isMember(Personnel $personnel, Message $record): bool
    {
        $conversation = $record->conversation;

        return $conversation !== null
            && $conversation->memberships()->where('personnel_id', $personnel->getKey())->exists();
    }
}
