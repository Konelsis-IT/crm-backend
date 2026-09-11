<?php

declare(strict_types=1);

namespace App\Services\Chat;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cevrimici / yaziyor bilgisi (D-83). Tabloya yazilmaz; onbellekte kisa omurlu
 * anahtarlar: kalp atisi 120 sn, yaziyor 8 sn. Tarayici her esitleme
 * isteginde kalp atisi gonderir; 90 sn icinde atis varsa kisi cevrimicidir.
 */
final class ChatPresence
{
    public const HEARTBEAT_TTL = 120;

    public const ONLINE_WINDOW = 90;

    public const TYPING_TTL = 8;

    public function __construct(private readonly Cache $cache) {}

    public function heartbeat(int $personnelId): void
    {
        $this->cache->put($this->presenceKey($personnelId), time(), self::HEARTBEAT_TTL);
    }

    public function isOnline(int $personnelId): bool
    {
        $seen = $this->cache->get($this->presenceKey($personnelId));

        return is_int($seen) && (time() - $seen) <= self::ONLINE_WINDOW;
    }

    /**
     * @param  list<int>  $personnelIds
     * @return list<int>
     */
    public function onlineIds(array $personnelIds): array
    {
        return array_values(array_filter(array_unique($personnelIds), fn (int $id): bool => $this->isOnline($id)));
    }

    public function typing(int $conversationId, int $personnelId): void
    {
        $this->cache->put($this->typingKey($conversationId, $personnelId), time(), self::TYPING_TTL);
    }

    /**
     * Sohbette su an yazanlar (istisna: kisinin kendisi).
     *
     * @param  list<int>  $memberIds
     * @return list<int>
     */
    public function typingIn(int $conversationId, array $memberIds, int $exceptId): array
    {
        $typing = [];

        foreach ($memberIds as $memberId) {
            if ($memberId === $exceptId) {
                continue;
            }

            $since = $this->cache->get($this->typingKey($conversationId, $memberId));

            if (is_int($since) && (time() - $since) <= self::TYPING_TTL) {
                $typing[] = $memberId;
            }
        }

        return $typing;
    }

    private function presenceKey(int $personnelId): string
    {
        return 'chat:presence:'.$personnelId;
    }

    private function typingKey(int $conversationId, int $personnelId): string
    {
        return 'chat:typing:'.$conversationId.':'.$personnelId;
    }
}
