<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\AnnouncementAudience;
use App\Models\Personnel\Personnel;
use App\Query\Notification\AudienceQueries;
use App\Services\Authorization\PermissionKey;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Collection;

/**
 * Gonderenin hangi kitlelere bildirim atabilecegini (Shield izinleri
 * notify:team / department / role / personnel / all) ve secilen kitlenin
 * alicilarini cozer (D-82). system_admin her kitleye gonderebilir.
 */
final class AudienceResolver
{
    public function __construct(private readonly AudienceQueries $queries) {}

    /**
     * @return list<AnnouncementAudience>
     */
    public function permittedKinds(Personnel $sender): array
    {
        if (! $sender->isActive() || ! SchemaReadiness::hasBatch('B05')) {
            return [];
        }

        // system_admin her kitleye gonderebilir (Shield super_admin izinleri
        // acikca atanana kadar da).
        if (app(RoleResolver::class)->isSystemAdmin($sender)) {
            return AnnouncementAudience::cases();
        }

        return array_values(array_filter(
            AnnouncementAudience::cases(),
            fn (AnnouncementAudience $kind): bool => $sender->can(PermissionKey::custom($kind->permission())),
        ));
    }

    public function permits(Personnel $sender, AnnouncementAudience $kind): bool
    {
        return in_array($kind, $this->permittedKinds($sender), true);
    }

    /**
     * @param  list<int>  $personnelIds
     * @return array{recipients: Collection<int, Personnel>, label: string}
     */
    public function resolve(AnnouncementAudience $kind, ?int $audienceId, array $personnelIds, Personnel $sender): array
    {
        return match ($kind) {
            AnnouncementAudience::Team => [
                'recipients' => $this->queries->team((int) $sender->getKey()),
                'label' => __('announcement.audiences.team_of', ['name' => $sender->full_name]),
            ],
            AnnouncementAudience::Department => [
                'recipients' => $audienceId !== null ? $this->queries->department($audienceId) : new Collection,
                'label' => __('announcement.audiences.department_of', ['name' => $audienceId !== null ? ($this->queries->orgUnitLabel($audienceId) ?? '-') : '-']),
            ],
            AnnouncementAudience::Role => [
                'recipients' => $audienceId !== null ? $this->queries->role($audienceId) : new Collection,
                'label' => __('announcement.audiences.role_of', ['name' => $audienceId !== null ? ($this->queries->roleLabel($audienceId) ?? '-') : '-']),
            ],
            AnnouncementAudience::Personnel => [
                'recipients' => $this->queries->personnel(array_map('intval', $personnelIds)),
                'label' => __('announcement.audiences.personnel_count', ['count' => count($personnelIds)]),
            ],
            AnnouncementAudience::All => [
                'recipients' => $this->queries->all(),
                'label' => __('announcement.audiences.all'),
            ],
        };
    }
}
