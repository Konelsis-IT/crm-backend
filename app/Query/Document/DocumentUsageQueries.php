<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseDocument;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\ContractDocument;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Models\Acquisition\TenderNotice;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Models\Document\Document;
use App\Models\Party\Party;
use App\Models\Party\PartyCertificate;
use App\Models\Party\PartyLicense;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ChecklistTemplates;
use App\Support\Acquisition\CostLists;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokuman sayfasindaki "Bagli kayitlar" (D-181, 9 Ekim 2026 kullanici:
 * "Dokuman icinden ilgili yuklendigi teklife erisemiyorum"): belgenin
 * revizyonlarinin bagli oldugu kayitlar, kayit basina bir satir ve orada
 * hangi rolde durdugu.
 *
 * Kaynaklar: teklif belgeleri (proposal_documents, surum -> teklif), teklif
 * kapsam listesi (proposal_version_scopes) ve maliyet listesi (B51), potansiyel
 * is belgeleri (kontrol listesi maddesi / ek belgeler) ve kapsam listesi,
 * tekliften gelen potansiyel is, sozlesme belgeleri, ihale kaynak belgesi,
 * firma sertifikasi / lisansi. Projeye bag (documents.project_id) dokuman
 * kartinda ayrica gosterildigi icin burada tekrar edilmez.
 *
 * Yetki burada degil: ekran her kaydi kendi gorme politikasi ve detay
 * sayfasiyla suzer.
 */
final class DocumentUsageQueries
{
    /**
     * @return list<array{record: Model, title: string, roles: list<string>}>
     */
    public function forDocument(Document $document): array
    {
        $revisionIds = $document->revisions()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $usages = [];

        if ($revisionIds !== []) {
            $this->proposalDocuments($usages, $revisionIds);
        }

        if (SchemaReadiness::hasBatch('B43')) {
            $this->proposalScopes($usages, $document, $revisionIds);
            $this->caseDocuments($usages, $document);
        }

        if ($revisionIds !== [] && CostLists::enabled()) {
            $this->costLists($usages, $revisionIds);
        }

        if (SchemaReadiness::hasBatch('B29')) {
            $this->caseScopes($usages, $document);
        }

        if ($revisionIds !== []) {
            $this->contracts($usages, $revisionIds);
            $this->tenders($usages, $revisionIds);
            $this->parties($usages, $revisionIds);
        }

        return array_values(array_map(static function (array $usage): array {
            $roles = [];

            foreach (array_unique($usage['roles']) as $role) {
                $version = $usage['versions'][$role] ?? null;
                $roles[] = $version === null
                    ? $role
                    : $role.' · '.__($version['current'] ? 'document.usage.current_version' : 'document.usage.older_version', ['no' => $version['no']]);
            }

            return ['record' => $usage['record'], 'title' => $usage['title'], 'roles' => $roles];
        }, $usages));
    }

    /**
     * Teklif belgeleri: surumlerden teklife; teklifin potansiyel isi de eklenir.
     *
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     * @param  list<int>  $revisionIds
     */
    private function proposalDocuments(array &$usages, array $revisionIds): void
    {
        $rows = ProposalDocument::query()
            ->with(['version.proposal.businessCase.codes'])
            ->whereIn('document_revision_id', $revisionIds)
            ->get();

        foreach ($rows as $row) {
            /** @var ProposalDocument $row */
            $this->addProposal($usages, $row->version, self::label($row->document_role));
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     * @param  list<int>  $revisionIds
     */
    private function proposalScopes(array &$usages, Document $document, array $revisionIds): void
    {
        $rows = ProposalVersionScope::query()
            ->with(['version.proposal.businessCase.codes'])
            ->where(static function ($query) use ($document, $revisionIds): void {
                $query->where('scope_document_id', $document->getKey());

                if ($revisionIds !== []) {
                    $query->orWhereIn('scope_document_revision_id', $revisionIds);
                }
            })
            ->get();

        foreach ($rows as $scope) {
            /** @var ProposalVersionScope $scope */
            $this->addProposal($usages, $scope->version, __('business_case_scope.fields.scope_document').' · '.self::scopeLabel($scope->scope_type));
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     * @param  list<int>  $revisionIds
     */
    private function costLists(array &$usages, array $revisionIds): void
    {
        $rows = ProposalVersionScopeDocument::query()
            ->with(['versionScope.version.proposal.businessCase.codes'])
            ->whereIn('document_revision_id', $revisionIds)
            ->get();

        foreach ($rows as $row) {
            /** @var ProposalVersionScopeDocument $row */
            $scope = $row->versionScope;
            $this->addProposal($usages, $scope?->version, self::label($row->document_role).' · '.self::scopeLabel($scope?->scope_type));
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     */
    private function caseDocuments(array &$usages, Document $document): void
    {
        $rows = BusinessCaseDocument::query()
            ->with(['businessCase.codes'])
            ->where('document_id', $document->getKey())
            ->get();

        foreach ($rows as $row) {
            /** @var BusinessCaseDocument $row */
            $role = $row->item_code !== null
                ? __('document.usage.checklist_item', [
                    'item' => $row->item_code.'. '.ChecklistTemplates::label((string) $row->template_code, (string) $row->item_code),
                ])
                : __('document_bundle.folders.extra');

            $this->addCase($usages, $row->businessCase, $role);
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     */
    private function caseScopes(array &$usages, Document $document): void
    {
        $rows = BusinessCaseScope::query()
            ->with(['businessCase.codes'])
            ->where('scope_document_id', $document->getKey())
            ->get();

        foreach ($rows as $scope) {
            /** @var BusinessCaseScope $scope */
            $this->addCase($usages, $scope->businessCase, __('business_case_scope.fields.scope_document').' · '.self::scopeLabel($scope->scope_type));
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     * @param  list<int>  $revisionIds
     */
    private function contracts(array &$usages, array $revisionIds): void
    {
        $rows = ContractDocument::query()
            ->with(['version.contract'])
            ->whereIn('document_revision_id', $revisionIds)
            ->get();

        foreach ($rows as $row) {
            /** @var ContractDocument $row */
            $contract = $row->version?->contract;

            if ($contract instanceof Contract) {
                $role = self::label($row->document_role).' · '.__('document.usage.version', ['no' => $row->version->version_no]);
                $this->add($usages, $contract, (string) $contract->contract_no, $role);
            }
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     * @param  list<int>  $revisionIds
     */
    private function tenders(array &$usages, array $revisionIds): void
    {
        $rows = TenderNoticeVersion::query()
            ->with(['notice'])
            ->whereIn('source_document_revision_id', $revisionIds)
            ->get();

        foreach ($rows as $row) {
            /** @var TenderNoticeVersion $row */
            $notice = $row->notice;

            if ($notice instanceof TenderNotice) {
                $this->add($usages, $notice, (string) $notice->title, __('document.usage.tender_source'));
            }
        }
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     * @param  list<int>  $revisionIds
     */
    private function parties(array &$usages, array $revisionIds): void
    {
        foreach ([PartyCertificate::class => 'document.usage.party_certificate', PartyLicense::class => 'document.usage.party_license'] as $model => $key) {
            $rows = $model::query()->with(['party'])->whereIn('document_revision_id', $revisionIds)->get();

            foreach ($rows as $row) {
                $party = $row->party;

                if ($party instanceof Party) {
                    $this->add($usages, $party, (string) $party->display_name, __($key));
                }
            }
        }
    }

    /**
     * Teklif satiri (rol + hangi surumde) ve teklifin potansiyel isi.
     *
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     */
    private function addProposal(array &$usages, ?ProposalVersion $version, string $role): void
    {
        $proposal = $version?->proposal;

        if (! $proposal instanceof Proposal) {
            return;
        }

        // Yeni surum ayni revizyonu yeniden baglar (D-158): her rolde yalniz belgenin
        // bulundugu en yeni surum yazilir ("Surum 3 (guncel)").
        $this->add($usages, $proposal, trim($proposal->proposal_no.' · '.$proposal->title, ' ·'), $role);
        $key = $proposal::class.':'.$proposal->getKey();
        $latest = $usages[$key]['versions'][$role] ?? null;

        if ($latest === null || (int) $version->version_no > $latest['no']) {
            $usages[$key]['versions'][$role] = [
                'no' => (int) $version->version_no,
                'current' => (int) $proposal->current_version_id === (int) $version->getKey(),
            ];
        }

        $this->addCase($usages, $proposal->businessCase, __('document.usage.via_proposal', ['no' => $proposal->proposal_no]));
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     */
    private function addCase(array &$usages, ?BusinessCase $case, string $role): void
    {
        if ($case === null) {
            return;
        }

        $code = $case->caseCode()?->formatted_code;
        $this->add($usages, $case, trim(($code ?? '').' · '.$case->title, ' ·'), $role);
    }

    /**
     * @param  array<string, array{record: Model, title: string, roles: list<string>}>  $usages
     */
    private function add(array &$usages, Model $record, string $title, string $role): void
    {
        $key = $record::class.':'.$record->getKey();
        $usages[$key] ??= ['record' => $record, 'title' => $title, 'roles' => []];
        $usages[$key]['roles'][] = $role;
    }

    private static function label(mixed $value): string
    {
        if ($value instanceof HasLabel) {
            return (string) $value->getLabel();
        }

        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private static function scopeLabel(mixed $type): string
    {
        if (is_string($type)) {
            $type = ProjectScopeType::tryFrom($type) ?? $type;
        }

        return self::label($type);
    }
}
