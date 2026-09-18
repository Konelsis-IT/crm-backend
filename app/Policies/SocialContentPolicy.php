<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SocialMedia\SocialContentStatus;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialComment;
use App\Models\SocialMedia\SocialContent;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\SocialMedia\SocialResponsibilityQueries;

/**
 * Sosyal medya icerigi (B31, D-106). Modulun TEK politikasidir: yorum, medya,
 * tepki, kategori, ozel gun, rakip hesap, istatistik gibi alt kayitlar bu
 * icerik uzerinden yetkilendirilir (PermissionSubjects + denetleyicilerde
 * `Gate::authorize(..., $content)`).
 *
 * Kimler:
 *  - Tam yetkili (Yonetici / Gelistirici): her sey; kendi icerigini de onaylar.
 *  - Sorumlu personel: Ayarlar'da secilen GOREVI bugun tutan aktif personel
 *    (SocialResponsibilityQueries). Yetkisi rol matrisinden degil gorevden
 *    gelir; gorev degisince matris yeniden yazilmaz.
 *  - Shield izinleri (Roller ekrani): ViewAny, View, Create, Update, Approve,
 *    Publish, Archive, ManageSettings.
 *  - Olusturan: kendi icerigini karar bekledigi surece duzenler, arsive
 *    kaldirir, tekrar onaya gonderir, acil onay ister.
 *  - Denetci: yalniz okur; yorum yazamaz, tepki veremez.
 *
 * Kurallar:
 *  - Cift goz: `Approve` izni olan kisi KENDI icerigine karar veremez.
 *  - Arsivdeki icerik yalniz goruntulenir ve arsivden cikarilir.
 *  - Paylasildi olarak isaretlenmis icerik duzenlenmez ve karara acilmaz.
 *  - Silme yoktur: silme / geri yukleme / cogaltma / siralama hep kapali.
 *
 * Siralama bilincli: ucuz denetimler (rol, izin, olusturan) once, veritabanina
 * giden sorumluluk denetimi EN SON calisir.
 */
final class SocialContentPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel)
            || $this->permits($personnel, 'viewAny')
            || $this->isResponsible($personnel);
    }

    /** Arsivdeki icerik de goruntulenir. */
    public function view(Personnel $personnel, SocialContent $record): bool
    {
        return $this->canRead($personnel)
            || $this->permits($personnel, 'view')
            || $this->isResponsible($personnel);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'create')
            || $this->isResponsible($personnel);
    }

    /**
     * Duzenleme (metin, plan, platform ve butun medya islemleri). Olusturan,
     * `Update` izni varsa onaylanmis icerigini de duzenler (icerik yeniden onaya
     * duser); izni yoksa yalniz karar bekleyen / geri donen icerigini duzenler.
     */
    public function update(Personnel $personnel, SocialContent $record): bool
    {
        if ($record->isArchived() || $record->isPublished()) {
            return false;
        }

        if ($this->hasFullAccess($personnel)) {
            return true;
        }

        if ($this->isCreator($personnel, $record)
            && ($this->permits($personnel, 'update') || $this->isOpenForCreator($record))) {
            return true;
        }

        return $this->isResponsible($personnel);
    }

    /**
     * Karar: onayla / reddet / revize iste. Cift goz kurali: izinli kisi kendi
     * icerigine karar veremez; tam yetkili verebilir.
     */
    public function approve(Personnel $personnel, SocialContent $record): bool
    {
        if ($record->isArchived() || $record->isPublished()) {
            return false;
        }

        if ($this->hasFullAccess($personnel)) {
            return true;
        }

        return ! $this->isCreator($personnel, $record) && $this->permits($personnel, 'approve');
    }

    /** Reddedilen ya da revize istenen icerigi tekrar onaya gonderme. */
    public function resubmit(Personnel $personnel, SocialContent $record): bool
    {
        if ($record->isPublished() || ! ($record->status?->canBeResubmitted() ?? false)) {
            return false;
        }

        return $this->hasFullAccess($personnel)
            || $this->isCreator($personnel, $record)
            || $this->isResponsible($personnel);
    }

    public function archive(Personnel $personnel, SocialContent $record): bool
    {
        return ! $record->isArchived() && $this->canArchive($personnel, $record);
    }

    /** Arsivden cikarma: arsive kaldirabilen herkes. */
    public function unarchive(Personnel $personnel, SocialContent $record): bool
    {
        return $record->isArchived() && $this->canArchive($personnel, $record);
    }

    /** Paylasildi olarak isaretleme (durum kosulunu servis denetler). */
    public function publish(Personnel $personnel, SocialContent $record): bool
    {
        if ($record->isArchived()) {
            return false;
        }

        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'publish')
            || $this->isResponsible($personnel);
    }

    /** Paylasim isaretini geri alma: yalniz tam yetkili ve sorumlu personel. */
    public function unpublish(Personnel $personnel, SocialContent $record): bool
    {
        if ($record->isArchived() || ! $record->isPublished()) {
            return false;
        }

        return $this->hasFullAccess($personnel) || $this->isResponsible($personnel);
    }

    /** Yorum ve isaret: icerigi goren herkes; denetci yazamaz. */
    public function comment(Personnel $personnel, SocialContent $record): bool
    {
        return ! $record->isArchived() && $this->canParticipate($personnel);
    }

    /** Begeni / begenmeme: icerigi goren herkes; denetci veremez. */
    public function react(Personnel $personnel, SocialContent $record): bool
    {
        return ! $record->isArchived() && $this->canParticipate($personnel);
    }

    /**
     * Isareti / yorumu cozuldu yapma ya da yeniden acma: icerigi olusturan,
     * yorumu yazan, sorumlu personel ve tam yetkili.
     * Kullanim: `Gate::authorize('resolveComment', [$content, $comment])`.
     */
    public function resolveComment(Personnel $personnel, SocialContent $record, SocialComment $comment): bool
    {
        if ($record->isArchived() || (int) $comment->content_id !== (int) $record->getKey()) {
            return false;
        }

        if ($this->hasFullAccess($personnel)) {
            return true;
        }

        if (! $this->canParticipate($personnel)) {
            return false;
        }

        return $this->isCreator($personnel, $record)
            || $this->isAuthor($personnel, $comment)
            || $this->isResponsible($personnel);
    }

    /** Acil onay isteme (tarih penceresi ve bekleme suresi servistedir). */
    public function requestUrgent(Personnel $personnel, SocialContent $record): bool
    {
        if ($record->isArchived()) {
            return false;
        }

        return $this->hasFullAccess($personnel)
            || $this->isCreator($personnel, $record)
            || $this->isResponsible($personnel);
    }

    /**
     * Modul verisi: kategoriler, ozel gunler, rakip / kurum hesaplari, hesap
     * baglantilari ve istatistik girisleri.
     */
    public function manageData(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'manageSettings')
            || $this->isResponsible($personnel);
    }

    /** Sorumlu gorevlerin secimi: sorumlu personel kendi yetkisini genisletemez. */
    public function manageSettings(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'manageSettings');
    }

    /**
     * Kisi duzeyi "karar verebilir mi" (kayittan bagimsiz; arayuzdeki
     * `approve_any`). Kayit duzeyi kural yine approve()'dadir.
     */
    public function approveAny(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'approve');
    }

    /** Kisi duzeyi "paylasildi isaretleyebilir mi" (arayuzdeki `publish_any`). */
    public function publishAny(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'publish')
            || $this->isResponsible($personnel);
    }

    public function delete(Personnel $personnel, SocialContent $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, SocialContent $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, SocialContent $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, SocialContent $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    /**
     * Durum gecisinin istedigi yetenek (`contents.status` ucu ve
     * `allowed_statuses` suzgeci): `Gate::authorize(self::transitionAbility($hedef), $content)`.
     * Taninmayan hedef, politikada karsiligi olmayan bir yetenek adi doner;
     * Gate tanimsiz yetenegi reddeder.
     */
    public static function transitionAbility(string $target): string
    {
        return match ($target) {
            SocialContentStatus::Approved->value,
            SocialContentStatus::Rejected->value,
            SocialContentStatus::RevisionRequested->value => 'approve',
            SocialContentStatus::Archived->value => 'archive',
            SocialContentStatus::Pending->value => 'resubmit',
            'unarchive' => 'unarchive',
            default => 'denied',
        };
    }

    private function canArchive(Personnel $personnel, SocialContent $record): bool
    {
        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'archive')
            || $this->isCreator($personnel, $record)
            || $this->isResponsible($personnel);
    }

    /** Gorebilen ve denetci olmayan: yorum yazar, tepki verir. */
    private function canParticipate(Personnel $personnel): bool
    {
        if ($this->hasFullAccess($personnel)) {
            return true;
        }

        if ($this->isAuditor($personnel)) {
            return false;
        }

        return $this->permits($personnel, 'view') || $this->isResponsible($personnel);
    }

    private function isCreator(Personnel $personnel, SocialContent $record): bool
    {
        return $personnel->isActive() && $record->isCreatedBy((int) $personnel->getKey());
    }

    private function isAuthor(Personnel $personnel, SocialComment $comment): bool
    {
        $authorId = $comment->getAttribute('created_by_personnel_id');

        return $personnel->isActive() && $authorId !== null && (int) $authorId === (int) $personnel->getKey();
    }

    /** Olusturanin izinsiz duzenleyebildigi durumlar: bekliyor, revize, reddedildi. */
    private function isOpenForCreator(SocialContent $record): bool
    {
        return in_array($record->status, [
            SocialContentStatus::Pending,
            SocialContentStatus::RevisionRequested,
            SocialContentStatus::Rejected,
        ], true);
    }

    /** Sorumluluk gorevden gelir; sonuc istek boyunca bellekte tutulur. */
    private function isResponsible(Personnel $personnel): bool
    {
        return $personnel->isActive()
            && app(SocialResponsibilityQueries::class)->isResponsible((int) $personnel->getKey());
    }
}
