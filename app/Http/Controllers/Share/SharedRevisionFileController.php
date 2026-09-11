<?php

declare(strict_types=1);

namespace App\Http\Controllers\Share;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Http\Controllers\Controller;
use App\Query\Document\DocumentShareQueries;
use App\Services\Document\FileDeliveryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paylasilan belgenin dosyasi (D-75): kimlik dogrulamasi yok, yetki
 * paylasim token'i uzerinden. Indirme yalniz `allow_download` acikken;
 * satir ici onizleme yalniz guvenli turlerde (FileDeliveryService kurali).
 */
final class SharedRevisionFileController extends Controller
{
    public function __invoke(Request $request, string $token, DocumentShareQueries $shares, FileDeliveryService $delivery): Response
    {
        $share = $shares->openByToken($token);

        abort_if($share === null, 404);

        $revision = $share->document?->displayRevision();
        $file = $revision?->files()
            ->where('file_role', DocumentRevisionFileRole::Original->value)
            ->with('fileObject')
            ->first();

        abort_if($file === null || $file->fileObject === null, 404);

        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        if ($disposition === 'download' && ! $share->allow_download) {
            abort(403);
        }

        if ($disposition === 'inline' && ! $file->fileObject->isInlinePreviewable() && ! $share->allow_download) {
            abort(403);
        }

        return $delivery->respond($file->fileObject, 'original', $disposition);
    }
}
