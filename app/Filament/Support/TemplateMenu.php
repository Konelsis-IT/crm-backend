<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Personnel\Personnel;
use App\Query\Document\TemplateDocumentQueries;
use App\Query\Personnel\OrganizationQueries;
use App\Support\Dashboard\Departments;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Ust cubuktaki Sablonlar menusunun icerigi (D-226, 19 Ekim 2026 kullanici:
 * "Sablonlar dashboard yerine artik ustteki menuye alinsin ... Tiklandiginda
 * ilgili personel'in bagli oldugu departmana ait belgeler ve herkesin ortak
 * erismesi gereken sablon belgeler dropdown seklinde gelecek. Ayrica
 * dropdown'da bir sablon ekleme butonu olsun + seklinde").
 *
 * Departman: kisinin calistigi birimlerin (kendi + ek gorevleri, D-218)
 * sablonlari; Is Gelistirme / Teklif calisaninda ayrica standart belgeler
 * (D-212). Ortak: sahip birimi bos sablonlar. Kisinin goremedigi ya da
 * dosyasi olmayan belge listeye girmez. Satirda Goruntule ve Indir (19 Ekim
 * 2026: "sablonlarin yaninda goruntule - indir butonu olmali direk indirme
 * olmamali"): goruntule PDF / gorselde dosyanin kendisi, Word / Excel'de
 * onizleme sayfasi (D-186), onizlenemiyorsa belgenin sayfasi.
 */
final class TemplateMenu
{
    private const STANDARD_DOCUMENT_UNITS = [Departments::BUSINESS_DEVELOPMENT, Departments::PROPOSALS];

    public function __construct(
        private readonly TemplateDocumentQueries $templates,
        private readonly OrganizationQueries $organization,
    ) {}

    /**
     * @return array{units: list<string>, department: list<array{id: int, title: string, ext: ?string, view: ?string, download: string}>, common: list<array{id: int, title: string, ext: ?string, view: ?string, download: string}>, createUrl: ?string, allUrl: ?string}
     */
    public function for(Personnel $person): array
    {
        $personnelId = (int) $person->getKey();
        $unitIds = $this->organization->unitIdsOf($personnelId);
        $department = [];
        $common = [];

        foreach ($this->templates->templates($unitIds) as $document) {
            $item = $this->item($document);

            if ($item === null) {
                continue;
            }

            if ($document->owner_org_unit_id === null) {
                $common[] = $item;
            } else {
                $department[] = $item;
            }
        }

        if (array_intersect($this->organization->unitCodesOf($personnelId), self::STANDARD_DOCUMENT_UNITS) !== []) {
            foreach ($this->templates->standardDocuments() as $document) {
                $item = $this->item($document);

                if ($item !== null) {
                    $department[] = $item;
                }
            }
        }

        $units = array_values(array_filter(array_map(
            fn (int $id): ?string => $this->organization->unit($id)?->name,
            $unitIds,
        )));

        return [
            'units' => $units,
            'department' => $department,
            'common' => $common,
            'createUrl' => DocumentResource::canCreate() ? $this->createUrl() : null,
            'allUrl' => DocumentResource::canAccess() ? $this->allUrl() : null,
        ];
    }

    /** "+ Sablon ekle": dokuman formu sablon turu ve kisinin departmaniyla acilir. */
    private function createUrl(): string
    {
        return DocumentResource::getUrl('create', ['sablon' => 1]);
    }

    /** "Tum sablonlar": Dokuman listesi sablon turune suzulu. */
    private function allUrl(): ?string
    {
        $typeId = $this->templates->templateTypeId();

        return $typeId === null
            ? null
            : DocumentResource::getUrl('index', ['filters' => ['document_type_id' => ['value' => $typeId]]]);
    }

    /** @return array{id: int, title: string, ext: ?string, view: ?string, download: string}|null */
    private function item(Document $document): ?array
    {
        if (! Gate::allows('view', $document)) {
            return null;
        }

        $revision = $document->displayRevision();
        $file = $revision instanceof DocumentRevision ? $this->originalFile($revision) : null;

        if ($file === null) {
            return null;
        }

        $object = $file->fileObject;
        $extension = $object !== null
            ? (string) ($object->extension ?: pathinfo((string) $object->original_name, PATHINFO_EXTENSION))
            : '';

        return [
            'id' => (int) $document->getKey(),
            'title' => (string) $document->title,
            'ext' => $extension !== '' ? Str::upper($extension) : null,
            'view' => FileLinks::previewFor($file) ?? RecordLinks::detailUrl($document, checkRecord: false),
            'download' => FileLinks::revisionFile($file, 'original', 'download'),
        ];
    }

    /** Onceden yuklenen orijinal dosya satiri; yuklenmemisse sorgulanir. */
    private function originalFile(DocumentRevision $revision): ?DocumentRevisionFile
    {
        if ($revision->relationLoaded('files')) {
            return $revision->files->first(
                fn (DocumentRevisionFile $file): bool => FormState::value($file->file_role) === DocumentRevisionFileRole::Original->value,
            );
        }

        return $revision->files()
            ->where('file_role', DocumentRevisionFileRole::Original->value)
            ->with('fileObject')
            ->first();
    }
}
