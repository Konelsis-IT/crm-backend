<?php

declare(strict_types=1);

namespace App\Services\Acquisition\Concerns;

use App\Enums\Acquisition\ContractVersionStatus;
use App\Enums\Acquisition\EstimateVersionStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\ContractVersion;
use App\Models\Acquisition\EstimateVersion;
use App\Models\Acquisition\ProposalVersion;
use BackedEnum;

/**
 * Surum cocuklari (13 SS8.2): parent surum onaylandiktan/yayimlandiktan
 * sonra icerik satirlari degismez; yalniz "durum mutable" isaretli kolonlar
 * guncellenebilir. Her surum cocugu servisi yazma oncesi bu kontrolu yapar.
 */
trait GuardsVersionChildren
{
    protected function assertProposalVersionEditable(int|string|null $versionId): void
    {
        $status = $this->versionStatus(ProposalVersion::class, ProposalVersionStatus::class, $versionId);

        if ($status === null || ! in_array($status, [ProposalVersionStatus::Draft, ProposalVersionStatus::Review], true)) {
            throw InvalidTransitionException::make(['from' => $status?->getLabel() ?? '-', 'to' => '-']);
        }
    }

    protected function assertContractVersionEditable(int|string|null $versionId): void
    {
        $status = $this->versionStatus(ContractVersion::class, ContractVersionStatus::class, $versionId);

        if ($status === null || ! in_array($status, [ContractVersionStatus::Draft, ContractVersionStatus::Review], true)) {
            throw InvalidTransitionException::make(['from' => $status?->getLabel() ?? '-', 'to' => '-']);
        }
    }

    protected function assertEstimateVersionEditable(int|string|null $versionId): void
    {
        $status = $this->versionStatus(EstimateVersion::class, EstimateVersionStatus::class, $versionId);

        if ($status === null || ! in_array($status, [EstimateVersionStatus::Draft, EstimateVersionStatus::Reviewed], true)) {
            throw InvalidTransitionException::make(['from' => $status?->getLabel() ?? '-', 'to' => '-']);
        }
    }

    /**
     * Surumun durumunu enum olarak okur. Eloquent `value()` model cast'ini
     * uyguladigi icin deger enum gelebilir; ham string de guvenle donusturulur.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    private function versionStatus(string $model, string $enum, int|string|null $versionId): ?BackedEnum
    {
        if ($versionId === null || (int) $versionId <= 0) {
            return null;
        }

        $raw = $model::query()->whereKey((int) $versionId)->value('status');

        if ($raw instanceof $enum) {
            return $raw;
        }

        if ($raw instanceof BackedEnum) {
            $raw = $raw->value;
        }

        return $raw === null ? null : $enum::from((string) $raw);
    }
}
