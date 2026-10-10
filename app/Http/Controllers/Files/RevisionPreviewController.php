<?php

declare(strict_types=1);

namespace App\Http\Controllers\Files;

use App\Enums\Document\FileObjectStatus;
use App\Filament\Support\FileLinks;
use App\Http\Controllers\Controller;
use App\Models\Document\DocumentRevisionFile;
use App\Services\Document\Preview\OfficePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel / CSV / Word onizleme sayfasi (D-186, ozellik documents.office_preview;
 * rota RequireFeature ile kapali ozellikte 404). Yetki RevisionFileController
 * ile aynidir: dosyanin bagli oldugu dokuman uzerinden DocumentPolicy::view.
 * Onizleme indirme sayilmaz (Personel Hareketleri'ne yalniz indirme yazilir).
 *
 * Sorgu: sheet = gosterilecek tablo sayfasi (0'dan).
 */
final class RevisionPreviewController extends Controller
{
    public function __invoke(Request $request, DocumentRevisionFile $file, OfficePreviewService $preview): Response
    {
        $revision = $file->revision;
        $document = $revision?->document;
        $object = $file->fileObject;

        abort_if($document === null || $object === null, 404);

        Gate::authorize('view', $document);

        abort_if($object->status !== FileObjectStatus::Active || ! OfficePreviewService::supports($object->original_name), 404);

        $html = $preview->render($object, max(0, $request->integer('sheet')), FileLinks::revisionFile($file, 'original', 'download'));

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-cache',
            // Onizleme sayfasi betik calistirmaz; yalniz satir ici stil.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            'Referrer-Policy' => 'same-origin',
        ]);
    }
}
