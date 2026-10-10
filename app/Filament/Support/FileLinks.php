<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Platform\Feature;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Project\ProjectPhoto;
use App\Services\Document\Preview\OfficePreviewService;
use App\Services\Platform\FeatureFlags;
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

    /**
     * Indirmeden goruntuleme baglantisi (D-186, ozellik documents.office_preview):
     * PDF / gorsel / duz metin satir ici, Excel / CSV / Word onizleme sayfasi;
     * ozellik kapaliysa ya da tur onizlenemiyorsa null.
     */
    public static function previewFor(?DocumentRevisionFile $file): ?string
    {
        $object = $file?->fileObject;

        if ($file === null || $object === null || ! FeatureFlags::enabled(Feature::DocumentOfficePreview)) {
            return null;
        }

        if ($object->isInlinePreviewable()) {
            return self::revisionFile($file, 'original', 'inline');
        }

        return OfficePreviewService::supports($object->original_name)
            ? route(self::routeName('files.revision-preview'), ['file' => $file->getKey()])
            : null;
    }

    /** Revizyonun orijinalinin indirmeden goruntuleme baglantisi (D-186); yoksa null. */
    public static function revisionView(DocumentRevision $revision): ?string
    {
        return self::previewFor(self::originalRow($revision));
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
