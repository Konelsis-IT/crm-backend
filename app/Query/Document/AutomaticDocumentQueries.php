<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Platform\Feature;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Document\FileObject;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;

/**
 * Otomatik sirket belgeleri (D-176, 8 Ekim 2026 kullanici talimati: "Genel
 * katalog'un otomatik gelmesi gerekirdi ... her teklif icin gidip o belgeyi
 * tekrar sistemde cogaltma vs. yapmayacak. Sadece veritabanindan iliski
 * yapacaksin. Otomatik gelecek.").
 *
 * - Teklife otomatik gelen belgeler okuma aninda hesaplanir: teklif basina
 *   satir, dosya kopyasi ya da yeni revizyon yoktur. Dokumanlar'da belge
 *   guncellenince (yeni revizyon ya da yeni belge) butun teklifler hemen yenisini
 *   gosterir.
 * - Her tur icin o turun kullanilabilir en yeni belgesi alinir
 *   (FixedDocumentQueries::latestOfType: arsiv / gecersiz / yerine gecilmis
 *   olmayan, revizyonu olan, en son acilan belge). Ayni turde birden fazla
 *   belge Dokumanlar'a yuklenmisse yalniz en yenisi gosterilir: katalog sirketin
 *   tek guncel katalogudur, eskisi yeni belgeyle yerini birakir.
 * - Gosterilen revizyon Document::displayRevision(): yayimlanmis revizyon,
 *   yoksa en son acilan; asil dosyasi olmayan belge gosterilmez.
 * - Yeni bir otomatik tur eklemek icin PROPOSAL_TYPE_CODES'a tur kodu yazmak
 *   yeter. Referans listeleri belge degil yapisal veri olacagi icin (ayri
 *   modul) REF buraya eklenmez.
 * - D-184: teklifin Dokumanlar tablosunda bu belgeler diger belgeler gibi satir
 *   olarak gorunur (ProposalDocumentRows; "Otomatik" etiketi ve ayri bolum yok).
 */
final class AutomaticDocumentQueries
{
    /** @var list<string> Her teklifte otomatik gorunen dokuman turu kodlari. */
    public const PROPOSAL_TYPE_CODES = ['KAT'];

    /**
     * Otomatik belgenin yerini aldigi eski teklif belgesi rolu: o roldeki eski
     * satirlar (B29 "Genel kataloğu ekle") ayni belgeyi iki kez gostermesin diye
     * otomatik belge varken listelenmez.
     *
     * @var array<string, ProposalDocumentRole>
     */
    private const LEGACY_ROLES = ['KAT' => ProposalDocumentRole::Catalog];

    /** @var list<array{code: string, document: Document, revision: DocumentRevision, file: FileObject}>|null */
    private ?array $proposalDocuments = null;

    public function __construct(private readonly FixedDocumentQueries $fixed) {}

    /** Ozellik acik ve dokuman semasi kurulu mu. */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B06') && FeatureFlags::enabled(Feature::AutomaticDocuments);
    }

    /**
     * Her teklifte otomatik gorunen belgeler (tur sirasiyla).
     *
     * @return list<array{code: string, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    public function forProposals(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return $this->proposalDocuments ??= $this->resolve(self::PROPOSAL_TYPE_CODES);
    }

    /**
     * Otomatik belgenin karsiladigi eski roller (deger listesi).
     *
     * @return list<string>
     */
    public function replacedProposalRoles(): array
    {
        $roles = [];

        foreach ($this->forProposals() as $entry) {
            if (isset(self::LEGACY_ROLES[$entry['code']])) {
                $roles[] = self::LEGACY_ROLES[$entry['code']]->value;
            }
        }

        return $roles;
    }

    /**
     * @param  list<string>  $codes
     * @return list<array{code: string, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    private function resolve(array $codes): array
    {
        $entries = [];

        foreach ($codes as $code) {
            $document = $this->fixed->latestOfType($code);
            $revision = $document?->displayRevision();

            if ($document === null || $revision === null) {
                continue;
            }

            $revision->loadMissing('files.fileObject');
            $file = $revision->files
                ->first(static fn (DocumentRevisionFile $row): bool => $row->file_role === DocumentRevisionFileRole::Original)
                ?->fileObject;

            if (! $file instanceof FileObject) {
                continue;
            }

            $entries[] = ['code' => $code, 'document' => $document, 'revision' => $revision, 'file' => $file];
        }

        return $entries;
    }
}
