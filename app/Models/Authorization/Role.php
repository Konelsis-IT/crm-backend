<?php

declare(strict_types=1);

namespace App\Models\Authorization;

use App\Models\Personnel\Position;
use App\Policies\RolePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie'nin Role modelinin projeye ozel alt sinifi; yalniz Policy
 * baglamak icin var (config/permission.php 'models.role').
 */
#[UsePolicy(RolePolicy::class)]
class Role extends SpatieRole
{
    /** Bu rol bir pozisyonun rolu ise o pozisyon (D-81, B03A). */
    public function position(): HasOne
    {
        return $this->hasOne(Position::class, 'role_id');
    }
}
