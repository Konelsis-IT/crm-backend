<?php

declare(strict_types=1);

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\Document\DocumentRevisionFile;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\FileDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Doküman revizyon dosyasi indirme / onizleme (D-71). Yetki, dosyanin bagli
 * oldugu dokuman uzerinden (DocumentPolicy::view) kontrol edilir; orijinalin
 * indirilmesi Personel Hareketleri'ne yazilir (kontrollu dokuman izi) — kayit
 * servis uzerinden, kendi transaction'i icinde atilir (D-75 duzeltmesi).
 *
 * Sorgu: variant = original|thumbnail, disposition = inline|download.
 */
final class RevisionFileController extends Controller
{
    public function __invoke(
        Request $request,
        DocumentRevisionFile $file,
        FileDeliveryService $delivery,
        DocumentRevisionService $revisions,
    ): Response {
        $revision = $file->revision;
        $document = $revision?->document;

        abort_if($document === null || $file->fileObject === null, 404);

        Gate::authorize('view', $document);

        $variant = $request->query('variant') === 'thumbnail' ? 'thumbnail' : 'original';
        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        if ($variant === 'original' && $disposition === 'download') {
            $revisions->recordDownload($revision, $file->fileObject);
        }

        return $delivery->respond($file->fileObject, $variant, $disposition);
    }
}
