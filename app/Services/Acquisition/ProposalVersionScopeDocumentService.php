<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\GuardsVersionChildren;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif kapsamina bagli belge satiri (B51, D-181: Maliyet listesi). Surum
 * cocugudur: yalniz parent surum taslak / incelemedeyken eklenir ya da
 * silinir; satir degistirilmez (yeni revizyon yeni satirdir).
 */
final class ProposalVersionScopeDocumentService extends AbstractService
{
    use GuardsVersionChildren;

    protected string $orderBy = 'sort_order';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertProposalVersionEditable($this->versionIdOf($data['proposal_version_scope_id'] ?? null));

        return parent::create($data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var ProposalVersionScopeDocument $current */
        $current = $this->show($record);
        $this->assertProposalVersionEditable($this->versionIdOf($current->proposal_version_scope_id));

        return parent::delete($current);
    }

    private function versionIdOf(mixed $scopeId): ?int
    {
        $versionId = ProposalVersionScope::query()->whereKey((int) $scopeId)->value('proposal_version_id');

        return $versionId === null ? null : (int) $versionId;
    }
}
