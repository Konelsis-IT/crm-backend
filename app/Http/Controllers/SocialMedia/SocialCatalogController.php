<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\FileObject;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Query\Document\FixedDocumentQueries;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\FileDeliveryService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sirket katalogu (DMS sabit belge turu KAT) - "Ilham ve Rakipler" alanindaki
 * okuyucu (D-106, F11).
 *
 * Katalog Dokumanlar'a bir kez yuklenir; burada guncel revizyonunun ozgun
 * dosyasi gosterilir. Yetki DMS izniyle degil modulun kendi `viewAny`
 * yetkisiyle verilir: sosyal medya ekibi Dokumanlar iznine sahip olmasa da
 * katalogu modul icinde okuyabilir. Dosya FileDeliveryService ile akar;
 * indirme, kontrollu dokuman izi olarak Personel Hareketleri'ne yazilir
 * (DocumentRevisionService::recordDownload). Onizleme adresi her zaman satir
 * icidir ve iz birakmaz.
 */
final class SocialCatalogController extends Controller
{
    private const FILE_ROUTE = 'filament.admin.social.catalog.file';

    public function info(Request $request, FixedDocumentQueries $documents): JsonResponse
    {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        [$document, $revision, $file] = $this->resolve($documents);

        if ($document === null || $revision === null || $file === null) {
            return response()->json([
                'available' => false,
                'title' => $document?->title,
                'revision_label' => null,
                'preview_url' => null,
                'download_url' => null,
                'previewable' => false,
                'mime' => null,
                'file_name' => null,
                'size_human' => null,
                'updated_at' => null,
            ]);
        }

        $url = $this->fileUrl();
        $previewable = $url !== null && $file->isInlinePreviewable();

        return response()->json([
            'available' => $url !== null,
            'title' => (string) $document->title,
            'revision_label' => $this->revisionLabel($revision),
            'preview_url' => $previewable ? $url : null,
            'download_url' => $url === null ? null : $url.'?disposition=download',
            'previewable' => $previewable,
            'mime' => (string) $file->mime_type,
            'file_name' => (string) $file->original_name,
            'size_human' => $file->humanSize(),
            'updated_at' => ($revision->issued_at ?? $revision->created_at)?->toIso8601String(),
        ]);
    }

    /** Katalog dosyasi: `disposition = inline|download`. */
    public function file(
        Request $request,
        FixedDocumentQueries $documents,
        FileDeliveryService $delivery,
        DocumentRevisionService $revisions,
    ): Response {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        [, $revision, $file] = $this->resolve($documents);
        abort_if($revision === null || $file === null, 404);

        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        if ($disposition === 'download') {
            $revisions->recordDownload($revision, $file);
        }

        return $delivery->respond($file, 'original', $disposition);
    }

    /**
     * Katalog belgesi, gosterilecek revizyonu ve ozgun dosyasi. DMS kurulu
     * degilse ya da katalog henuz yuklenmediyse ilgili parcalar null doner.
     *
     * @return array{0: ?Document, 1: ?DocumentRevision, 2: ?FileObject}
     */
    private function resolve(FixedDocumentQueries $documents): array
    {
        if (! SchemaReadiness::hasBatch('B06')) {
            return [null, null, null];
        }

        $document = $documents->catalogDocument();
        $revision = $document?->displayRevision();

        return [$document, $revision, $revision?->originalFile()];
    }

    /**
     * Revizyon etiketi (`social_content.messages.catalog_revision`, :code);
     * anahtar yoksa Dokumanlar ekranlarindaki "Rev. X" yazimi kullanilir.
     */
    private function revisionLabel(DocumentRevision $revision): ?string
    {
        if (blank($revision->revision_code)) {
            return null;
        }

        $key = 'social_content.messages.catalog_revision';

        return Lang::has($key)
            ? (string) __($key, ['code' => $revision->revision_code])
            : 'Rev. '.$revision->revision_code;
    }

    /** Kok-goreli dosya adresi; rota henuz tanimli degilse null. */
    private function fileUrl(): ?string
    {
        try {
            return route(self::FILE_ROUTE, [], false);
        } catch (Throwable) {
            return null;
        }
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B31') && FeatureFlags::enabled('social_media.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }
}
