<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Report\ReportReviewMode;
use App\Enums\Report\ReportStatus;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\Report\ReportQueries;

/**
 * Rapor (D-86): her aktif personel rapor yazar; raporu yazar, inceleyen,
 * yazarin yoneticileri (gizli degilse) ve yetkili gorur. Gizli raporlar
 * (kisi hakkinda degerlendirme) konu personele gosterilmez; konu personelin
 * yoneticileri, yazar, inceleyen ve `ViewConfidential:Report` izni gorur.
 * Yazar taslagi duzenler, gonderir, inceleme baslamadan geri ceker, taslagi
 * siler; inceleyen onaylar / revizyon ister / reddeder.
 */
final class ReportPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, Report $record): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        if ($this->isAuthor($personnel, $record) || $this->isReviewer($personnel, $record) || $this->hasFullAccess($personnel)) {
            return true;
        }

        if ($record->is_confidential) {
            return $this->permits($personnel, 'viewConfidential')
                || $this->managesSubject($personnel, $record);
        }

        return $this->isAuditor($personnel)
            || $this->permits($personnel, 'view')
            || $this->managesAuthor($personnel, $record)
            || (int) $record->subject_personnel_id === (int) $personnel->getKey();
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function update(Personnel $personnel, Report $record): bool
    {
        return $record->status->isEditable()
            && ($this->isAuthor($personnel, $record) || $this->hasFullAccess($personnel));
    }

    public function submit(Personnel $personnel, Report $record): bool
    {
        return $this->update($personnel, $record);
    }

    /** Gonderilmis raporu inceleme baslamadan geri cekme. */
    public function withdraw(Personnel $personnel, Report $record): bool
    {
        return $record->status === ReportStatus::Submitted
            && $record->reviewed_at === null
            && ($this->isAuthor($personnel, $record) || $this->hasFullAccess($personnel));
    }

    /**
     * Inceleme: atanmis inceleyen ya da yetkili; yazar kendi raporunu
     * inceleyemez, inceleme gerektirmeyen taslakta karar verilmez.
     */
    public function review(Personnel $personnel, Report $record): bool
    {
        if (! $record->status->isReviewable() || $this->isAuthor($personnel, $record)) {
            return false;
        }

        if ($this->isReviewer($personnel, $record)) {
            return true;
        }

        $needsReview = ($record->template()?->reviewMode() ?? ReportReviewMode::None) !== ReportReviewMode::None;

        return $needsReview && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'review'));
    }

    public function delete(Personnel $personnel, Report $record): bool
    {
        return $record->status === ReportStatus::Draft
            && ($this->isAuthor($personnel, $record) || $this->hasFullAccess($personnel));
    }

    /** IK gorusu taslagini yazma yetkisi (Shield: AuthorHrEvaluation:Report). */
    public function authorHrEvaluation(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'authorHrEvaluation');
    }

    /** Gizli raporlari (kisi degerlendirmeleri) gorme yetkisi. */
    public function viewConfidential(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'viewConfidential');
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Report $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Report $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Report $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function isAuthor(Personnel $personnel, Report $record): bool
    {
        return (int) $record->author_personnel_id === (int) $personnel->getKey();
    }

    private function isReviewer(Personnel $personnel, Report $record): bool
    {
        return $record->reviewer_personnel_id !== null
            && (int) $record->reviewer_personnel_id === (int) $personnel->getKey();
    }

    private function managesAuthor(Personnel $personnel, Report $record): bool
    {
        return app(ReportQueries::class)->managesPersonnel((int) $personnel->getKey(), (int) $record->author_personnel_id);
    }

    private function managesSubject(Personnel $personnel, Report $record): bool
    {
        return $record->subject_personnel_id !== null
            && app(ReportQueries::class)->managesPersonnel((int) $personnel->getKey(), (int) $record->subject_personnel_id);
    }
}
