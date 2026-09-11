<?php

declare(strict_types=1);

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\Project\ProjectPhoto;
use App\Services\Document\FileDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saha fotografi goruntuleme / indirme (D-71). Yetki ProjectPhotoPolicy::view.
 * Sorgu: variant = original|thumbnail, disposition = inline|download.
 */
final class ProjectPhotoController extends Controller
{
    public function __invoke(Request $request, ProjectPhoto $photo, FileDeliveryService $delivery): Response
    {
        abort_if($photo->file === null, 404);

        Gate::authorize('view', $photo);

        $variant = $request->query('variant') === 'thumbnail' ? 'thumbnail' : 'original';
        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        return $delivery->respond($photo->file, $variant, $disposition);
    }
}
