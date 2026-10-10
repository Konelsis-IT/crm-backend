<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;

/**
 * Bir belgenin ekrandaki tek satiri (B43, D-155): baslik, revizyon, dosya adi ve
 * yetki kontrollu indirme baglantisi (FileLinks). Kontrol listesi, potansiyel
 * is belgeleri, teklif belgeleri ve teklif kapsami ayni bicimi kullanir.
 */
final class DocumentLine
{
    /**
     * Revizyon verilmezse belgenin en yeni revizyonu. Iliskiler onceden
     * yuklendiyse (revisions.files.fileObject) ek sorgu olmaz.
     *
     * @return array{title: string, revision: string|null, file: string|null, url: string|null, preview: string|null}|null
     */
    public static function info(?Document $document, ?DocumentRevision $revision = null): ?array
    {
        if ($document === null && $revision === null) {
            return null;
        }

        $document ??= $revision?->document;
        /** @var DocumentRevision|null $revision */
        $revision ??= $document?->revisions->sortByDesc('revision_no')->first();
        $row = $revision?->files
            ->first(static fn (DocumentRevisionFile $row): bool => $row->file_role === DocumentRevisionFileRole::Original);
        $file = $row?->fileObject;

        return [
            'title' => (string) ($document?->title ?? $revision?->title ?? '-'),
            'revision' => $revision?->revision_code,
            'file' => $file?->original_name,
            'url' => $revision !== null ? FileLinks::revisionOriginal($revision, 'download') : null,
            // D-186: indirmeden goruntuleme (ozellik kapaliysa null; cip eskisi gibi indirir).
            'preview' => FileLinks::previewFor($row),
        ];
    }

    /**
     * "dosya.xlsx · Rev 02" ya da baslik.
     *
     * @param  array{title: string, revision: string|null, file: string|null, url: string|null}|null  $info
     */
    public static function text(?array $info, bool $withTitle = false): string
    {
        if ($info === null) {
            return '-';
        }

        $parts = [];

        if ($withTitle || ! filled($info['file'])) {
            $parts[] = $info['title'];
        }

        if (filled($info['file'])) {
            $parts[] = (string) $info['file'];
        }

        if (filled($info['revision'])) {
            $parts[] = __('document.short.revision', ['code' => $info['revision']]);
        }

        return implode(' · ', $parts);
    }
}
