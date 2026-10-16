<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItemFile;
use App\Services\Document\FileDeliveryService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Is belgesi goruntuleme / indirme (D-219, B57). Yalniz isi gorebilenler.
 * Sorgu: variant = original|thumbnail, disposition = inline|download.
 */
final class WorkItemFileController extends Controller
{
    public function __invoke(Request $request, WorkItemFile $file, FileDeliveryService $delivery): Response
    {
        abort_unless(SchemaReadiness::hasBatch('B57'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel, 403);

        $item = $file->workItem;
        abort_if($item === null || $file->fileObject === null, 404);
        abort_unless(Gate::forUser($user)->allows('view', $item), 403);

        $variant = $request->query('variant') === 'thumbnail' ? 'thumbnail' : 'original';
        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        return $delivery->respond($file->fileObject, $variant, $disposition);
    }
}
