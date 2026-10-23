<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Enums\Document\FileObjectStatus;
use App\Filament\Support\FileLinks;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItemFile;
use App\Services\Document\Preview\OfficePreviewService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Is belgesinin Excel / CSV / Word onizlemesi (D-243; kullanici 23 Ekim 2026:
 * "Belgelerde onizlenebilir olsun. Biliyorsun bizim excel ve pdf on izlememiz
 * zaten var. Ayni mantik devam"). Dokumanlardaki onizlemenin (D-186,
 * RevisionPreviewController) aynisi; yetki isi gorebilmektir. Onizleme
 * indirme sayilmaz.
 *
 * Sorgu: sheet = gosterilecek tablo sayfasi (0'dan).
 */
final class WorkItemFilePreviewController extends Controller
{
    public function __invoke(Request $request, WorkItemFile $file, OfficePreviewService $preview): Response
    {
        abort_unless(SchemaReadiness::hasBatch('B57'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel, 403);

        $item = $file->workItem;
        $object = $file->fileObject;
        abort_if($item === null || $object === null, 404);
        abort_unless(Gate::forUser($user)->allows('view', $item), 403);
        abort_if($object->status !== FileObjectStatus::Active || ! OfficePreviewService::supports($object->original_name), 404);

        $html = $preview->render($object, max(0, $request->integer('sheet')), FileLinks::workItemFile($file, 'download'), $request->boolean('embed'));

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-cache',
            'Content-Security-Policy' => OfficePreviewService::CONTENT_SECURITY_POLICY,
            'Referrer-Policy' => 'same-origin',
        ]);
    }
}
