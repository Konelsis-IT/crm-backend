<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Activity\PersonnelActivity;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Report\Report;
use App\Models\WorkRequest\WorkRequest;
use App\Policies\PersonnelPolicy;
use App\Services\Authorization\RoleResolver;
use Database\Factories\PersonnelFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

/**
 * Personel: hem sirket calisani karti hem de sisteme giris hesabidir.
 */
#[Table('personnel')]
#[Fillable([
    'full_name', 'email', 'password', 'photo_path', 'national_id', 'phone',
    'job_title', 'title_id', 'org_unit_id', 'locale', 'timezone', 'status',
    'personnel_no', 'hired_on',
])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(PersonnelPolicy::class)]
class Personnel extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    /** @use HasFactory<PersonnelFactory> */
    use HasAuditColumns, HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => PersonnelStatus::class,
            'hired_on' => 'date',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'failed_login_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Personnel $personnel): void {
            $personnel->setAttribute('normalized_email', self::normalizeEmail($personnel->getAttribute('email')));
        });
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    /** Unvan (B26, D-88); gorevden (Position/job_title) kasitli olarak ayridir. */
    public function title(): BelongsTo
    {
        return $this->belongsTo(PersonnelTitle::class, 'title_id');
    }

    /** Organizasyon birimi/gorev gecmisi. */
    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(PersonnelAssignment::class, 'personnel_id');
    }

    /** Kimin kime raporladiginin gecmisi (bu personel taraf olarak). */
    public function reportingRelationships(): HasMany
    {
        return $this->hasMany(ReportingRelationship::class, 'personnel_id');
    }

    /** Su an gecerli dogrudan amir, varsa. Departman yoneticisinden ayridir. */
    public function currentManager(): ?self
    {
        $relation = $this->reportingRelationships()
            ->where('relation_type', 'line')
            ->whereNull('valid_until')
            ->first();

        return $relation?->manager;
    }

    /** Pozisyon ataması (birden fazla olabilir; is_primary + allocation_pct). */
    public function positionAssignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class, 'personnel_id');
    }

    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(Competency::class, 'personnel_competencies', 'personnel_id', 'competency_id')
            ->withPivot(['level', 'note']);
    }

    public function competencyRecords(): HasMany
    {
        return $this->hasMany(PersonnelCompetency::class, 'personnel_id');
    }

    public function personnelCertifications(): HasMany
    {
        return $this->hasMany(PersonnelCertification::class, 'personnel_id');
    }

    public function trainingAttendances(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class, 'personnel_id');
    }

    /** Bu personelin yaptigi islemler (Personel Hareketleri). */
    public function activities(): HasMany
    {
        return $this->hasMany(PersonnelActivity::class, 'personnel_id');
    }

    public function isActive(): bool
    {
        return $this->status === PersonnelStatus::Active;
    }

    /**
     * Sirkette calisan ve kendisine yazilabilen / bildirim gonderilebilen /
     * talep acilabilen personel: aktif, davet edilmis (henuz giris yapmamis)
     * ve izinde. Askida ve ayrilmis personel kapsam disidir (11 Eylul 2026:
     * yeni eklenen 'davet edildi' durumundaki personel sohbet ve duyuruda
     * gorunmuyordu).
     *
     * @return list<string>
     */
    public static function reachableStatuses(): array
    {
        return [PersonnelStatus::Active->value, PersonnelStatus::Invited->value, PersonnelStatus::OnLeave->value];
    }

    public function isReachable(): bool
    {
        return in_array($this->status->value, self::reachableStatuses(), true);
    }

    public function scopeReachable(Builder $query): Builder
    {
        return $query->whereIn('status', self::reachableStatuses());
    }

    /** Filament kullanici menusunde gosterilen ad. */
    public function getFilamentName(): string
    {
        return (string) $this->full_name;
    }

    /** Filament kullanici menusundeki personel fotografi. */
    public function getFilamentAvatarUrl(): ?string
    {
        $path = $this->photo_path;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Aktif personel ve en az bir rolu olanlar paneli acabilir (M03, D-16;
     * D-81 ile pozisyon rolleri): Yonetici ve Gelistirici disindaki roller
     * yalniz Shield izinlerinin verdigi ekranlari gorur (D-90).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $roles = app(RoleResolver::class);

        return match ($panel->getId()) {
            'admin' => $roles->hasFullAccess($this) || $roles->isAuditor($this) || $roles->hasAnyRole($this),
            default => false,
        };
    }

    /** Bu kayda bagli raporlar (B10A, D-86). */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'subject_personnel_id');
    }

    /** Bu kisiye gelen talepler (B11B). */
    public function incomingWorkRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class, 'target_personnel_id');
    }

    /** Bu kisinin actigi talepler (B11B). */
    public function requestedWorkRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class, 'requester_personnel_id');
    }
}
