<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Report\WorkItemSource;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\Report\ReportQueries;

/**
 * Is panosu karti (B36, D-115).
 *
 * - Her aktif personel kendi panosunu kullanir: kendi kartini gorur, girer,
 *   tasir ve (elle girdigi kartta) siler. Shield kutulari (ViewAny, View,
 *   Create, Update, Delete: WorkItem) bu islemlerin kapisidir; kayit
 *   duzeyinde ayrica sahiplik aranir.
 * - Amir ve departman yoneticisi ekibinin kartlarini gorur ve tasir (Ekip
 *   panosu); baskasinin karti yalniz bu yetkiyle tasinir.
 * - `ViewAll:WorkItem`: Yonetim panosu ve butun kartlar.
 * - `ControlMatrix:WorkItem`: kontrol matrisini doldurma (IK).
 * - `ViewAttentionCard:WorkItem`: personel kartindaki Dikkat karti; kisi
 *   kendi kartini hicbir yetkiyle gormez.
 *
 * 23 Eylul 2026 (D-116): Analizler menusu, personel kartindaki Haftalik
 * kontrol sekmesi ve matrisin salt okunur hali ust yonetimindir
 * (App\Services\Authorization\ExecutiveDirectory); matrisi yalniz IK doldurur.
 */
final class WorkItemPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, WorkItem $record): bool
    {
        if (! $this->viewAny($personnel)) {
            return false;
        }

        return $this->ownsOrCreated($personnel, $record)
            || $this->isAwaited($personnel, $record)
            || $this->viewAll($personnel)
            || $this->isAuditor($personnel)
            || $this->manages($personnel, (int) $record->personnel_id);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, WorkItem $record): bool
    {
        if (! ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'))) {
            return false;
        }

        return $this->ownsOrCreated($personnel, $record)
            || $this->hasFullAccess($personnel)
            || $this->manages($personnel, (int) $record->personnel_id);
    }

    /** Elle girilen kart silinir; otomatik kart oneriye geri donmez, durumu degistirilir. */
    public function delete(Personnel $personnel, WorkItem $record): bool
    {
        if ($this->hasFullAccess($personnel)) {
            return true;
        }

        return $this->permits($personnel, 'delete')
            && $record->source === WorkItemSource::Manual
            && $this->ownsOrCreated($personnel, $record);
    }

    /** Yonetim panosu ve butun kartlar. */
    public function viewAll(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'viewAll');
    }

    /** Ekip panosu: amiri oldugu ya da yonettigi departmanin personeli var. */
    public function viewTeam(Personnel $personnel): bool
    {
        return $this->viewAll($personnel)
            || app(ReportQueries::class)->teamPersonnelIds((int) $personnel->getKey()) !== [];
    }

    /**
     * Analizler menusu (Is raporlari: analiz panosu, sure raporu) - D-116,
     * kullanici karari: "Analizler kismi yine sadece Yonetici tarafindan
     * gorulebilir olmalidir."
     */
    public function viewAnalytics(Personnel $personnel): bool
    {
        return $personnel->isActive() && $this->seesCompanyWide($personnel);
    }

    /** Kontrol matrisi ekrani: dolduran (IK) ya da okuyan (ust yonetim). */
    public function controlMatrix(Personnel $personnel): bool
    {
        return $this->fillControl($personnel) || $this->reviewControl($personnel);
    }

    /**
     * Matrisi doldurma (D-116): "Sadece IK tarafindan doldurulabilecek."
     * Ust yonetim doldurmaz, yalniz okur.
     */
    public function fillControl(Personnel $personnel): bool
    {
        if (! $personnel->isActive() || $this->isExecutive($personnel)) {
            return false;
        }

        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'controlMatrix');
    }

    /** Matrisin salt okunur hali (gunluk ve haftalik): ust yonetim. */
    public function reviewControl(Personnel $personnel): bool
    {
        return $personnel->isActive() && $this->seesCompanyWide($personnel);
    }

    /**
     * Personel kartindaki Haftalik kontrol sekmesi (D-116): yalniz ust
     * yonetim gorur; kisi kendi kartinda gormez.
     */
    public function viewControl(Personnel $personnel, Personnel $subject): bool
    {
        if (! $personnel->isActive() || $personnel->is($subject)) {
            return false;
        }

        return $this->seesCompanyWide($personnel);
    }

    /** Dikkat karti: ozel yetki; kisi kendi kartini gormez. */
    public function viewAttentionCard(Personnel $personnel, Personnel $subject): bool
    {
        if (! $personnel->isActive() || $personnel->is($subject)) {
            return false;
        }

        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'viewAttentionCard');
    }

    /** Personel kartindaki Isler sekmesi: kisinin kendisi, amiri ya da tum kartlari goren. */
    public function viewPersonnelItems(Personnel $personnel, Personnel $subject): bool
    {
        return $this->viewAny($personnel)
            && ($personnel->is($subject) || $this->viewAll($personnel) || $this->isAuditor($personnel) || $this->manages($personnel, (int) $subject->getKey()));
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, WorkItem $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, WorkItem $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, WorkItem $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    /** Is bu kisiden bekleniyor: karti gorur (23 Eylul 2026 kullanici istegi). */
    private function isAwaited(Personnel $personnel, WorkItem $record): bool
    {
        return $record->waiting_personnel_id !== null
            && (int) $record->waiting_personnel_id === (int) $personnel->getKey();
    }

    private function ownsOrCreated(Personnel $personnel, WorkItem $record): bool
    {
        $id = (int) $personnel->getKey();

        return (int) $record->personnel_id === $id || (int) $record->created_by_personnel_id === $id;
    }

    private function manages(Personnel $personnel, int $subjectId): bool
    {
        return app(ReportQueries::class)->managesPersonnel((int) $personnel->getKey(), $subjectId);
    }
}
