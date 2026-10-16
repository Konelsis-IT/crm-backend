<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Enums\Acquisition\OfferStatus;
use App\Models\Acquisition\Proposal;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Contracts\Support\Htmlable;

/**
 * "Yeni teklif surumu" (D-186, 9 Ekim 2026 kullanici karari: "Komple teklifte
 * yeni surum secenegi olacak. Personel 'Yeni teklif surumu' action butonuna
 * tiklarsa onceki bilgilerin tamami klasik bir duzenleme ekrani gibi gelecek ve
 * var olan belgeleri de isterse carpi butonu ile kaldirip yeni surume yeni
 * belgeleri yukleyebilecektir. Ancak bu hamle teklif surumunu 2 yapacaktir").
 *
 * Adres: /admin/proposals/{kayit}/new-version. Teklif duzenleme ekraninin
 * aynisi (EditProposal: ayni sihirbaz, guncel surumun butun alanlari,
 * kapsamlari ve belgeleri dolu gelir, renkli form dugmeleri ve kaydedince
 * detay sayfasi); baslik "Yeni surum (Surum N+1)". Kaydedince surum N+1 acilir
 * (AcquisitionIntakeService::newProposalVersion). Taslak kaydi ve durum
 * dugmesi bu ekranda yok. Yetki duzenlemeyle aynidir (teklifi guncelleme).
 */
class NewProposalVersion extends EditProposal
{
    protected function isNewVersion(): bool
    {
        return true;
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();

        return trim(($proposal->proposal_no ?? '').' · '.__('proposal.new_version.heading', ['no' => $this->nextVersionNo()]), ' ·');
    }

    /** Baslik sayfanin isi ("Yeni teklif surumu"); teklif kodu ust gezinme satirinda (D-205). */
    public function getHeading(): string|Htmlable|null
    {
        return __('proposal.new_version.heading', ['no' => $this->nextVersionNo()]);
    }

    public function getBreadcrumb(): string
    {
        return __('proposal.new_version.breadcrumb');
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();
        $current = $proposal->currentVersion;

        // D-195 (B55): yeni surum Verilecek baslar, onceki surum kendi teklif durumunu korur.
        if (SchemaReadiness::hasBatch('B55') && $current?->offer_status !== null) {
            return __('proposal.new_version.subheading_offer', [
                'current' => $current->version_no,
                'next' => $this->nextVersionNo(),
                'offer' => OfferStatus::ToBeSubmitted->getLabel(),
                'status' => $current->offer_status->getLabel(),
            ]);
        }

        return __('proposal.new_version.subheading', [
            'current' => $current?->version_no ?? '-',
            'next' => $this->nextVersionNo(),
        ]);
    }

    private function nextVersionNo(): int
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();

        return (int) $proposal->versions()->max('version_no') + 1;
    }
}
