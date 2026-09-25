<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification\PanelNotification;
use App\Models\Personnel\Personnel;

/**
 * Tum bildirimler (D-122): herkes yalniz kendi bildirimlerini gorur ve
 * okundu / okunmadi isaretler. Bildirim elle olusturulmaz, silinmez.
 * Roller ekraninda izin kutusu yoktur (filament-shield exclude).
 */
final class PanelNotificationPolicy
{
    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, PanelNotification $record): bool
    {
        return $this->owns($personnel, $record);
    }

    public function update(Personnel $personnel, PanelNotification $record): bool
    {
        return $this->owns($personnel, $record);
    }

    public function create(Personnel $personnel): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, PanelNotification $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    private function owns(Personnel $personnel, PanelNotification $record): bool
    {
        return $personnel->isActive()
            && $record->notifiable_type === $personnel->getMorphClass()
            && (int) $record->notifiable_id === (int) $personnel->getKey();
    }
}
