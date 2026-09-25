<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Controller;
use App\Models\Chat\Message;
use App\Models\Notification\PanelNotification;
use App\Models\Personnel\Personnel;
use App\Query\Chat\ChatQueries;
use App\Query\Notification\NotificationInboxQueries;
use App\Services\Chat\ChatPresenter;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Masaustu (Windows) bildirimi ve bildirim sesi icin hafif besleme (D-126,
 * 25 Eylul 2026 kullanici istegi): kisinin en son okunmamis zil bildirimi ve
 * en son okunmamis sohbet mesaji. Tarayicidaki konelsis-alerts.js 20 saniyede
 * bir okur; sekme arka plandayken de calisir (sohbet yoklamasi arka planda
 * durur). Yalniz okur, hicbir seyi okundu yapmaz.
 */
final class AlertFeedController extends Controller
{
    public function __construct(
        private readonly NotificationInboxQueries $notifications,
        private readonly ChatQueries $chat,
        private readonly ChatPresenter $presenter,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $me = $request->user();
        abort_unless($me instanceof Personnel && $me->isActive(), 403);

        return response()->json([
            'notification' => $this->notification($me),
            'chat' => $this->chatMessage($me),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function notification(Personnel $me): ?array
    {
        if (! FeatureFlags::enabled('notifications.database') || ! SchemaReadiness::hasBatch('B00')) {
            return null;
        }

        $latest = $this->notifications->latestUnread($me);

        if (! $latest instanceof PanelNotification) {
            return null;
        }

        return [
            'id' => (string) $latest->getKey(),
            'title' => $latest->title(),
            'body' => Str::limit($latest->body(), 180, '…'),
            'url' => $latest->targetUrl(),
            'at' => $latest->created_at?->toIso8601String(),
            'unread' => $this->notifications->unreadCount($me),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function chatMessage(Personnel $me): ?array
    {
        if (! SchemaReadiness::hasBatch('B12A') || ! FeatureFlags::enabled('chat.admin_ui')) {
            return null;
        }

        try {
            $result = $this->chat->latestUnread((int) $me->getKey());
        } catch (Throwable) {
            return null;
        }

        $message = $result['message'];

        if (! $message instanceof Message) {
            return null;
        }

        $conversation = $message->conversation;
        $author = (string) ($message->author?->full_name ?? __('chat.values.unknown_person'));
        $group = $conversation !== null && ! $conversation->isDirect() && filled($conversation->title) ? (string) $conversation->title : null;

        return [
            'id' => (int) $message->getKey(),
            'conversation_id' => (int) $message->conversation_id,
            'title' => $group !== null ? $author.' · '.$group : $author,
            'body' => $this->presenter->preview($message),
            'url' => Dashboard::getUrl(['sohbet' => (int) $message->conversation_id]),
            'at' => $message->sent_at?->toIso8601String(),
            'unread' => $result['total'],
        ];
    }
}
