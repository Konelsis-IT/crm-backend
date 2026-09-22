<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Filament\Resources\ProposalVersions\RelationManagers\DocumentsRelationManager as VersionDocumentsRelationManager;
use App\Models\Acquisition\Proposal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Teklifin belgeleri (22 Eylul 2026 kullanici karari: surumler, dokumanlar ve
 * raporlar teklif sayfasinin alt listelerindedir). Tum surumlerin belgeleri
 * surum numarasiyla listelenir; yeni belge guncel surume eklenir. Tablo ve
 * form surum sayfasindaki belge listesiyle aynidir.
 */
class DocumentsRelationManager extends VersionDocumentsRelationManager
{
    protected static string $relationship = 'versionDocuments';

    protected function targetVersionId(): ?int
    {
        /** @var Proposal $proposal */
        $proposal = $this->getOwnerRecord();

        return $proposal->current_version_id !== null ? (int) $proposal->current_version_id : null;
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->pushColumns([
                TextColumn::make('version.version_no')
                    ->label(__('proposal_version.fields.version_no')),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['version', 'documentRevision.document']));
    }
}
