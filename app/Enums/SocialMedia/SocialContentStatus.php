<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Icerik durumu (B31, D-106). Bes durum vardir; ilk durum "bekliyor"dur.
 *
 *  bekliyor        -> onaylandi, reddedildi, revize edilsin, arsiv
 *  revize edilsin  -> bekliyor (tekrar onaya gonder), onaylandi, reddedildi, arsiv
 *  reddedildi      -> bekliyor (tekrar onaya gonder), arsiv
 *  onaylandi       -> revize edilsin, arsiv, bekliyor (onayli icerik degisince
 *                     servis kendiliginden dondurur; kullanici hedefi degildir)
 *  arsiv           -> arsivden cikarma `status_before_archive` degerine doner
 *                     (bos ise bekliyor); hedef listesi yalniz bunun icindir.
 *
 * Silme yoktur; arsiv son duraktir ama geri alinabilir.
 */
enum SocialContentStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::RevisionRequested, self::Archived],
            self::RevisionRequested => [self::Pending, self::Approved, self::Rejected, self::Archived],
            self::Rejected => [self::Pending, self::Archived],
            self::Approved => [self::RevisionRequested, self::Archived, self::Pending],
            self::Archived => [self::Pending, self::Approved, self::Rejected, self::RevisionRequested],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** Karar durumlari: onaylandi, reddedildi, revize edilsin. */
    public function isDecision(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::RevisionRequested], true);
    }

    /** Bu duruma gecerken aciklama zorunlu mu (ret ve revize)? */
    public function requiresNote(): bool
    {
        return in_array($this, [self::Rejected, self::RevisionRequested], true);
    }

    /** Hazirlayanin "tekrar onaya gonder" diyebildigi durumlar. */
    public function canBeResubmitted(): bool
    {
        return in_array($this, [self::Rejected, self::RevisionRequested], true);
    }

    /** Acil onay istenebilen durumlar. */
    public function isAwaitingDecision(): bool
    {
        return in_array($this, [self::Pending, self::RevisionRequested], true);
    }

    /**
     * Plan, hatirlatma, pano ve "geciken" listelerine giren durumlar
     * (ayrica published_at bos ve planned_on dolu olmalidir).
     *
     * @return list<self>
     */
    public static function plannable(): array
    {
        return [self::Pending, self::RevisionRequested, self::Approved];
    }

    /**
     * @return list<string>
     */
    public static function plannableValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::plannable());
    }

    /** Filament rengi (pano widget'i). */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::RevisionRequested => 'info',
            self::Archived => 'gray',
        };
    }

    /**
     * React paletindeki renk adi. JSON'daki platform disi her `*_color`
     * anahtari bu degeri tasir (red, amber, emerald, sky, violet, stone, rose, teal).
     */
    public function uiColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
            self::RevisionRequested => 'violet',
            self::Archived => 'stone',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::RevisionRequested => Heroicon::OutlinedArrowPath,
            self::Archived => Heroicon::OutlinedArchiveBox,
        };
    }
}
