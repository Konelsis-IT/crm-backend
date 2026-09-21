<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyOrigin;
use App\Enums\Party\PartyStatus;
use App\Enums\Party\VisitPriority;
use App\Models\Acquisition\BusinessCase;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Address;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\ContactRelationship;
use App\Models\Party\OrganizationProfile;
use App\Models\Party\PartyAnnualReview;
use App\Models\Party\PartyCertificate;
use App\Models\Party\PartyLicense;
use App\Models\Party\PartyMeetingNote;
use App\Models\Party\PartyRole;
use App\Models\Party\PersonProfile;
use App\Models\Personnel\Personnel;
use App\Models\Reference\Country;
use App\Models\WorkRequest\WorkRequest;
use App\Policies\PartyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('parties')]
#[Fillable([
    'party_no', 'party_kind', 'display_name', 'normalized_name', 'country_code', 'default_locale',
    'duplicate_check_hash', 'status', 'merged_into_party_id', 'network_note', 'visit_priority',
    'origin', 'is_competitor',
])]
#[UsePolicy(PartyPolicy::class)]
class Party extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_kind' => PartyKind::class,
            'status' => PartyStatus::class,
            'visit_priority' => VisitPriority::class,
            'origin' => PartyOrigin::class,
            'is_competitor' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_party_id');
    }

    /** Arsivleyen personel (S3). */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'archived_by_personnel_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class, 'party_id');
    }

    public function organizationProfile(): HasOne
    {
        return $this->hasOne(OrganizationProfile::class, 'party_id');
    }

    public function personProfile(): HasOne
    {
        return $this->hasOne(PersonProfile::class, 'party_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'party_id');
    }

    public function communicationPoints(): HasMany
    {
        return $this->hasMany(CommunicationPoint::class, 'party_id');
    }

    /**
     * Kurumun kendi kanallari (B28): kisiye bagli olmayan noktalar
     * (santral, genel e-posta). Kisiye ait kanallar ContactRelationship'te.
     */
    public function ownCommunicationPoints(): HasMany
    {
        return $this->communicationPoints()->whereNull('contact_relationship_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ContactRelationship::class, 'organization_party_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(PartyLicense::class, 'party_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(PartyCertificate::class, 'party_id');
    }

    public function annualReviews(): HasMany
    {
        return $this->hasMany(PartyAnnualReview::class, 'party_id');
    }

    /** Bu tarafla yapilan gorusme notlari (B28). */
    public function meetingNotes(): HasMany
    {
        return $this->hasMany(PartyMeetingNote::class, 'party_id');
    }

    /** Faaliyet satirlari: proje tipi + faaliyet alani + alt faaliyet alani (B33). */
    public function activityAreas(): HasMany
    {
        return $this->hasMany(PartyActivityArea::class, 'party_id')->orderBy('id');
    }

    public function businessCases(): HasMany
    {
        return $this->hasMany(BusinessCase::class, 'primary_party_id');
    }

    /** Bu kayitla ilgili talepler (B11B). */
    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class, 'customer_party_id');
    }
}
