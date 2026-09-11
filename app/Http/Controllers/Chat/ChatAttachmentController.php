<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\MessageAttachment;
use App\Models\Personnel\Personnel;
use App\Query\Chat\ChatQueries;
use App\Services\Document\FileDeliveryService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sohbet eki goruntuleme / indirme (D-83). Yalniz sohbetin uyeleri.
 * Sorgu: variant = original|thumbnail, disposition = inline|download.
 */
final class ChatAttachmentController extends Controller
{
    public function __invoke(Request $request, MessageAttachment $attachment, ChatQueries $queries, FileDeliveryService $delivery): Response
    {
        abort_unless(SchemaReadiness::hasBatch('B12A'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel, 403);

        $message = $attachment->message;
        abort_if($message === null || $attachment->fileObject === null, 404);
        abort_if($queries->membership((int) $message->conversation_id, (int) $user->getKey()) === null, 403, __('exceptions.chat.not_a_member'));

        $variant = $request->query('variant') === 'thumbnail' ? 'thumbnail' : 'original';
        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        return $delivery->respond($attachment->fileObject, $variant, $disposition);
    }
}
