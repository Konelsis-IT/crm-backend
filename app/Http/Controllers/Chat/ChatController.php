<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Exceptions\AbstractException;
use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationMembership;
use App\Models\Chat\Message;
use App\Models\Personnel\Personnel;
use App\Query\Chat\ChatQueries;
use App\Services\Chat\ChatPresence;
use App\Services\Chat\ChatPresenter;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Sohbet JSON uclari (D-83). Panelin kimlik dogrulamali rota grubundadir;
 * tarayicidaki React arayuzu bu uclarla konusur. Yazma isleri servislerde,
 * okuma sorgulari ChatQueries'te; burasi yalniz dogrulama ve bicimleme.
 */
final class ChatController extends Controller
{
    public const SYNC_INTERVAL_MS = 4000;

    public const MESSAGES_INTERVAL_MS = 2500;

    public const MAX_UPLOAD_KB = 20480;

    public function __construct(
        private readonly ChatQueries $queries,
        private readonly ChatPresenter $presenter,
        private readonly ChatPresence $presence,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $this->presence->heartbeat((int) $me->getKey());

        return response()->json([
            'me' => $this->presenter->person($me, true),
            'conversations' => $this->conversationList($me),
            'poll' => ['sync' => self::SYNC_INTERVAL_MS, 'messages' => self::MESSAGES_INTERVAL_MS],
            'max_upload_kb' => self::MAX_UPLOAD_KB,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json(['conversations' => $this->conversationList($me)]);
    }

    /** Hafif esitleme: kalp atisi + liste (okunmamis, son sira, yaziyor). */
    public function sync(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $this->presence->heartbeat((int) $me->getKey());

        $conversations = $this->conversationList($me);

        return response()->json([
            'conversations' => $conversations,
            'unread_total' => array_sum(array_map(fn (array $row): int => (int) $row['unread'], $conversations)),
        ]);
    }

    public function createConversation(Request $request, ConversationService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('create', Conversation::class);

        $data = $request->validate([
            'personnel_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'personnel_ids' => ['nullable', 'array', 'max:50'],
            'personnel_ids.*' => ['integer'],
        ]);

        try {
            $conversation = filled($data['personnel_id'] ?? null)
                ? $service->direct((int) $data['personnel_id'])
                : $service->group((string) ($data['title'] ?? ''), (array) ($data['personnel_ids'] ?? []));
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        $conversation = $this->queries->conversationsFor((int) $me->getKey())->firstWhere('id', $conversation->getKey()) ?? $conversation->load('memberships.personnel.orgUnit', 'memberships.personnel.roles', 'latestMessage');
        $membership = $this->queries->membership((int) $conversation->getKey(), (int) $me->getKey());

        return response()->json([
            'conversation' => $membership !== null ? $this->presenter->conversation($conversation, $membership, $me, 0) : null,
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->me($request);
        $membership = $this->memberOrFail($conversation, $me);
        $this->presence->heartbeat((int) $me->getKey());

        $after = $request->filled('after') ? (int) $request->query('after') : null;
        $before = $request->filled('before') ? (int) $request->query('before') : null;
        $limit = min(100, max(10, (int) $request->query('limit', 50)));

        $messages = $this->queries->messages($conversation, $membership, $after, $before, $limit);
        $memberIds = $this->queries->memberIds((int) $conversation->getKey());

        return response()->json([
            'messages' => $messages->map(fn (Message $message): array => $this->presenter->message($message, $me))->values()->all(),
            'has_more' => $after === null && $messages->count() >= $limit,
            'last_sequence' => (int) $conversation->last_message_sequence,
            'typing' => $this->typingNames($conversation, $memberIds, (int) $me->getKey()),
            'online_ids' => $this->presence->onlineIds($memberIds),
        ]);
    }

    public function send(Request $request, Conversation $conversation, MessageService $service): JsonResponse
    {
        $me = $this->me($request);
        $this->memberOrFail($conversation, $me);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:'.MessageService::MAX_BODY],
            'link_url' => ['nullable', 'string', 'max:2000', 'url'],
            'document_id' => ['nullable', 'integer'],
            'file' => ['nullable', 'file', 'max:'.self::MAX_UPLOAD_KB],
        ]);

        $tempPath = null;
        $originalName = null;

        if ($request->hasFile('file') && $request->file('file')?->isValid()) {
            $upload = $request->file('file');
            $originalName = $upload->getClientOriginalName();
            $tempPath = $upload->store('chat/tmp', 'local');
        }

        try {
            $message = $service->send($conversation, [
                'body' => $data['body'] ?? null,
                'link_url' => $data['link_url'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'file_temp_path' => $tempPath,
                'file_original_name' => $originalName,
            ]);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['message' => $this->presenter->message($message, $me)]);
    }

    public function read(Request $request, Conversation $conversation, ConversationService $service): JsonResponse
    {
        $me = $this->me($request);
        $this->memberOrFail($conversation, $me);
        $data = $request->validate(['sequence' => ['required', 'integer', 'min:0']]);

        try {
            $service->markRead($conversation, (int) $data['sequence']);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['ok' => true]);
    }

    public function pin(Request $request, Conversation $conversation, ConversationService $service): JsonResponse
    {
        $me = $this->me($request);
        $this->memberOrFail($conversation, $me);

        try {
            $pinned = $service->togglePin($conversation);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['pinned' => $pinned]);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->memberOrFail($conversation, $me);
        $this->presence->typing((int) $conversation->getKey(), (int) $me->getKey());

        return response()->json(['ok' => true]);
    }

    public function deleteConversation(Request $request, Conversation $conversation, ConversationService $service): JsonResponse
    {
        $me = $this->me($request);
        $this->memberOrFail($conversation, $me);

        try {
            $service->clearForMe($conversation);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['ok' => true]);
    }

    public function deleteMessage(Request $request, Message $message, MessageService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('view', $message);

        try {
            $service->hideForMe($message);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['ok' => true]);
    }

    public function directory(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $people = $this->queries->directory($request->query('q'), (int) $me->getKey());
        $onlineIds = $this->presence->onlineIds($people->map(fn (Personnel $personnel): int => (int) $personnel->getKey())->all());

        return response()->json([
            'people' => $people
                ->map(fn (Personnel $personnel): array => $this->presenter->person($personnel, in_array((int) $personnel->getKey(), $onlineIds, true)))
                ->values()
                ->all(),
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $this->me($request);

        return response()->json([
            'documents' => $this->queries->documents($request->query('q'))
                ->map(fn ($document): array => [
                    'id' => (int) $document->getKey(),
                    'title' => (string) $document->title,
                    'document_no' => $document->document_no,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationList(Personnel $me): array
    {
        $conversations = $this->queries->conversationsFor((int) $me->getKey());
        $unread = $this->queries->unreadCounts((int) $me->getKey(), $conversations->map(fn (Conversation $conversation): int => (int) $conversation->getKey())->all());
        $rows = [];

        foreach ($conversations as $conversation) {
            /** @var ConversationMembership|null $mine */
            $mine = $conversation->memberships->firstWhere('personnel_id', (int) $me->getKey());

            if ($mine === null) {
                continue;
            }

            $memberIds = $conversation->memberships->map(fn (ConversationMembership $membership): int => (int) $membership->personnel_id)->all();
            $typingIds = $this->presence->typingIn((int) $conversation->getKey(), $memberIds, (int) $me->getKey());
            $row = $this->presenter->conversation($conversation, $mine, $me, $unread[(int) $conversation->getKey()] ?? 0, $typingIds);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['pinned'] !== $b['pinned']) {
                return $a['pinned'] ? -1 : 1;
            }

            return strcmp((string) $b['last_message_at'], (string) $a['last_message_at']);
        });

        return $rows;
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<string>
     */
    private function typingNames(Conversation $conversation, array $memberIds, int $exceptId): array
    {
        $typingIds = $this->presence->typingIn((int) $conversation->getKey(), $memberIds, $exceptId);

        if ($typingIds === []) {
            return [];
        }

        return $conversation->memberships()
            ->with('personnel')
            ->whereIn('personnel_id', $typingIds)
            ->get()
            ->map(fn (ConversationMembership $membership): ?string => $membership->personnel?->full_name)
            ->filter()
            ->values()
            ->all();
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B12A') && FeatureFlags::enabled('chat.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }

    private function memberOrFail(Conversation $conversation, Personnel $me): ConversationMembership
    {
        $membership = $this->queries->membership((int) $conversation->getKey(), (int) $me->getKey());
        abort_if($membership === null, 403, __('exceptions.chat.not_a_member'));

        return $membership;
    }

    private function failure(AbstractException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->userMessage()], 422);
    }
}
