<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\Chat\ConversationType;
use App\Enums\Chat\MembershipRole;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\Chat\InvalidParticipantException;
use App\Exceptions\Chat\NotAMemberException;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationMembership;
use App\Models\Chat\ConversationReadCursor;
use App\Models\Personnel\Personnel;
use App\Query\Chat\ChatQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sohbet yasam dongusu (D-83): birebir sohbet (tek), grup, sabitleme,
 * tek tarafli temizleme ve okundu imleci.
 *
 * Bilincli sadelestirme: okundu imleci ve sabitleme yuksek sikliktaki arayuz
 * durumudur; Personel Hareketleri kaydi yalniz sohbet olusturma ve temizleme
 * icin yazilir.
 */
final class ConversationService extends AbstractService
{
    protected string $orderBy = 'last_message_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly ChatQueries $queries,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Birebir sohbet: varsa doner, yoksa acar. */
    public function direct(int $otherPersonnelId): Conversation
    {
        $me = $this->actorId();

        if ($otherPersonnelId === $me) {
            throw InvalidParticipantException::make();
        }

        $other = $this->queries->findPersonnel($otherPersonnelId);

        if (! $other instanceof Personnel || ! $other->isReachable()) {
            throw InvalidParticipantException::make();
        }

        $pairKey = Conversation::pairKey($me, $otherPersonnelId);

        return $this->transactions->run(function () use ($me, $otherPersonnelId, $pairKey): Conversation {
            /** @var Conversation|null $existing */
            $existing = Conversation::query()->where('direct_pair_key', $pairKey)->lockForUpdate()->first();

            if ($existing !== null) {
                foreach ([$me, $otherPersonnelId] as $personnelId) {
                    $this->ensureMembership($existing, $personnelId, MembershipRole::Member);
                }

                return $existing;
            }

            /** @var Conversation $conversation */
            $conversation = parent::create([
                'conversation_type' => ConversationType::Direct->value,
                'scope_type' => 'none',
                'classification_id' => $this->queries->defaultClassificationId(),
                'history_policy' => 'full_history',
                'status' => 'active',
                'direct_pair_key' => $pairKey,
            ]);

            foreach ([$me, $otherPersonnelId] as $personnelId) {
                $this->ensureMembership($conversation, $personnelId, MembershipRole::Member);
            }

            return $conversation;
        });
    }

    /**
     * Grup sohbeti; olusturan sahip, digerleri uye.
     *
     * @param  list<int>  $memberIds
     */
    public function group(string $title, array $memberIds): Conversation
    {
        $me = $this->actorId();
        $title = trim($title);
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), fn (int $id): bool => $id > 0 && $id !== $me)));

        if ($title === '' || $memberIds === []) {
            throw InvalidParticipantException::make();
        }

        return $this->transactions->run(function () use ($me, $title, $memberIds): Conversation {
            /** @var Conversation $conversation */
            $conversation = parent::create([
                'conversation_type' => ConversationType::Group->value,
                'scope_type' => 'none',
                'title' => $title,
                'classification_id' => $this->queries->defaultClassificationId(),
                'history_policy' => 'full_history',
                'status' => 'active',
            ]);

            $this->ensureMembership($conversation, $me, MembershipRole::Owner);

            foreach ($memberIds as $personnelId) {
                $person = $this->queries->findPersonnel($personnelId);

                if ($person instanceof Personnel && $person->isReachable()) {
                    $this->ensureMembership($conversation, $personnelId, MembershipRole::Member);
                }
            }

            return $conversation;
        });
    }

    /** Sabitle / sabitlemeyi kaldir; yeni durumu doner. */
    public function togglePin(Conversation $conversation): bool
    {
        return $this->transactions->run(function () use ($conversation): bool {
            $membership = $this->membershipOf($conversation);
            $pinned = ! $membership->isPinned();
            $membership->forceFill(['pinned_at' => $pinned ? Carbon::now('UTC') : null])->save();

            return $pinned;
        });
    }

    /**
     * Tek tarafli silme: bu andan onceki mesajlar kisiye gorunmez, sabitleme
     * kalkar, imlec sona alinir. Karsi taraf etkilenmez.
     */
    public function clearForMe(Conversation $conversation): void
    {
        $this->transactions->run(function () use ($conversation): void {
            $membership = $this->membershipOf($conversation);
            $now = Carbon::now('UTC');

            $membership->forceFill(['history_visible_from' => $now, 'pinned_at' => null])->save();
            $this->writeCursor($conversation, (int) $membership->personnel_id, (int) $conversation->last_message_sequence);

            $this->recordActivity($conversation, 'cleared');
        });
    }

    /** Okundu imleci ileri alinir (geri gitmez). */
    public function markRead(Conversation $conversation, int $sequence): void
    {
        $this->transactions->run(function () use ($conversation, $sequence): void {
            $membership = $this->membershipOf($conversation);
            $this->writeCursor($conversation, (int) $membership->personnel_id, min($sequence, (int) $conversation->last_message_sequence));
        });
    }

    /** Oturumdaki kisinin uyeligi; uye degilse is hatasi. */
    public function membershipOf(Conversation $conversation, ?int $personnelId = null): ConversationMembership
    {
        $membership = $this->queries->membership((int) $conversation->getKey(), $personnelId ?? $this->actorId());

        if ($membership === null) {
            throw NotAMemberException::make();
        }

        return $membership;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'tur' => $record->getAttribute('conversation_type') instanceof ConversationType
                ? $record->getAttribute('conversation_type')->value
                : $record->getAttribute('conversation_type'),
            'baslik' => $record->getAttribute('title'),
        ];
    }

    private function ensureMembership(Conversation $conversation, int $personnelId, MembershipRole $role): void
    {
        $existing = $this->queries->membership((int) $conversation->getKey(), $personnelId);

        if ($existing !== null) {
            return;
        }

        $membership = new ConversationMembership([
            'conversation_id' => $conversation->getKey(),
            'personnel_id' => $personnelId,
            'role' => $role->value,
            'joined_at' => Carbon::now('UTC'),
            'is_muted' => false,
        ]);
        $membership->save();
    }

    private function writeCursor(Conversation $conversation, int $personnelId, int $sequence): void
    {
        /** @var ConversationReadCursor|null $cursor */
        $cursor = ConversationReadCursor::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('personnel_id', $personnelId)
            ->first();

        if ($cursor === null) {
            $cursor = new ConversationReadCursor([
                'conversation_id' => $conversation->getKey(),
                'personnel_id' => $personnelId,
                'last_read_sequence' => max(0, $sequence),
            ]);
            $cursor->save();

            return;
        }

        if ($sequence > (int) $cursor->last_read_sequence) {
            $cursor->forceFill(['last_read_sequence' => $sequence])->save();
        }
    }

    private function actorId(): int
    {
        $id = $this->actor->personnelId();

        if ($id === null) {
            throw ActorRequiredException::make();
        }

        return $id;
    }
}
