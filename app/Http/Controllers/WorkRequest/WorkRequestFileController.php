<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkRequest;

use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\WorkRequest\WorkRequestMessageFile;
use App\Services\Document\FileDeliveryService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Talep yazismasi eki goruntuleme / indirme (B32). Yalniz talebi gorebilenler.
 * Sorgu: variant = original|thumbnail, disposition = inline|download.
 */
final class WorkRequestFileController extends Controller
{
    public function __invoke(Request $request, WorkRequestMessageFile $file, FileDeliveryService $delivery): Response
    {
        abort_unless(SchemaReadiness::hasBatch('B32'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel, 403);

        $workRequest = $file->message?->workRequest;
        abort_if($workRequest === null || $file->fileObject === null, 404);
        abort_unless(Gate::forUser($user)->allows('view', $workRequest), 403);

        $variant = $request->query('variant') === 'thumbnail' ? 'thumbnail' : 'original';
        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        return $delivery->respond($file->fileObject, $variant, $disposition);
    }
}
