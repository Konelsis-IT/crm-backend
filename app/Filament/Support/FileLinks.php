<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Project\ProjectPhoto;
use Filament\Facades\Filament;

/**
 * Yetki kontrollu dosya baglantilari (D-71). Rotalar panelin kimlik
 * dogrulamali grubunda tanimlidir (AdminPanelProvider::authenticatedRoutes);
 * yetki kararini ilgili denetleyici sahip is nesnesi uzerinden verir.
 */
final class FileLinks
{
    public static function revisionFile(DocumentRevisionFile $file, string $variant = 'original', string $disposition = 'inline'): string
    {
        return route(self::routeName('files.revision'), [
            'file' => $file->getKey(),
            'variant' => $variant,
            'disposition' => $disposition,
        ]);
    }

    /** Revizyonun orijinal dosyasi; dosya yoksa null. */
    public static function revisionOriginal(DocumentRevision $revision, string $disposition = 'download'): ?string
    {
        $file = self::originalRow($revision);

        return $file === null ? null : self::revisionFile($file, 'original', $disposition);
    }

    /** Revizyonun orijinali satir ici gosterilebiliyorsa onizleme baglantisi. */
    public static function revisionPreview(DocumentRevision $revision): ?string
    {
        $file = self::originalRow($revision);

        if ($file === null || $file->fileObject === null || ! $file->fileObject->isInlinePreviewable()) {
            return null;
        }

        return self::revisionFile($file, 'original', 'inline');
    }

    /** Gorsel revizyonlar icin kucuk gorsel; gorsel degilse null. */
    public static function revisionThumbnail(DocumentRevision $revision): ?string
    {
        $file = self::originalRow($revision);

        if ($file === null || $file->fileObject === null || ! $file->fileObject->isImage()) {
            return null;
        }

        return self::revisionFile($file, 'thumbnail', 'inline');
    }

    public static function photo(ProjectPhoto $photo, string $variant = 'original', string $disposition = 'inline'): string
    {
        return route(self::routeName('files.photo'), [
            'photo' => $photo->getKey(),
            'variant' => $variant,
            'disposition' => $disposition,
        ]);
    }

    private static function originalRow(DocumentRevision $revision): ?DocumentRevisionFile
    {
        return $revision->files()
            ->where('file_role', DocumentRevisionFileRole::Original->value)
            ->with('fileObject')
            ->first();
    }

    private static function routeName(string $name): string
    {
        return Filament::getCurrentOrDefaultPanel()->generateRouteName($name);
    }
}
