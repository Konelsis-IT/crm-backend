<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Exceptions\AbstractException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialComment;
use App\Models\SocialMedia\SocialContent;
use App\Query\SocialMedia\SocialContentQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialCommentPresenter;
use App\Services\SocialMedia\SocialCommentService;
use App\Services\SocialMedia\SocialContentPresenter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Icerik yorumlari ve gorsel isaretleri icin JSON uclari (B31, D-106).
 * Yorumun kendi politikasi yoktur; yetki ust icerik uzerinden sorulur
 * (`comment`, `resolveComment`). Yazma SocialCommentService'te, bicimleme
 * sunum siniflarindadir. Her yanit `{ comment, content }` tasir; icerik taze
 * okunur ki sayaclar ve acik isaret sayilari guncel gelsin.
 */
final class SocialCommentController extends Controller
{
    public function __construct(
        private readonly SocialContentQueries $queries,
        private readonly SocialContentPresenter $contents,
        private readonly SocialCommentPresenter $comments,
    ) {}

    /** Yorum, yanit ya da isaretli yorum ekler. */
    public function store(Request $request, SocialContent $content, SocialCommentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('comment', $content);

        $data = $this->validated($request, [
            'body' => ['required', 'string', 'max:'.max(1, (int) config('konelsis.social_media.comment_max', 4000))],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'media_id' => ['nullable', 'integer', 'min:1', 'required_with:anchor'],
            'anchor' => ['nullable', 'array'],
            'anchor.shape' => ['required_with:anchor', 'string', Rule::in(SocialComment::SHAPES)],
            'anchor.x' => ['required_with:anchor', 'numeric', 'between:0,1'],
            'anchor.y' => ['required_with:anchor', 'numeric', 'between:0,1'],
            'anchor.w' => ['nullable', 'required_if:anchor.shape,'.SocialComment::SHAPE_RECT, 'numeric', 'between:0,1'],
            'anchor.h' => ['nullable', 'required_if:anchor.shape,'.SocialComment::SHAPE_RECT, 'numeric', 'between:0,1'],
        ]);

        try {
            $comment = $service->add($content, $data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->commentResponse($comment, (int) $content->getKey(), $me);
    }

    /** Yorumu / isareti cozuldu yapar ya da yeniden acar. */
    public function resolve(Request $request, SocialComment $comment, SocialCommentService $service): JsonResponse
    {
        $me = $this->me($request);

        $content = $comment->content;
        abort_if(! $content instanceof SocialContent, 404);

        Gate::authorize('resolveComment', [$content, $comment]);

        $data = $this->validated($request, [
            'resolved' => ['required', 'boolean'],
        ]);

        try {
            $resolved = $service->resolve($comment, (bool) $data['resolved']);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->commentResponse($resolved, (int) $content->getKey(), $me);
    }

    /** `{ comment, content }`: yorum iliskileriyle, icerik ayrinti bicimiyle. */
    private function commentResponse(SocialComment $comment, int $contentId, Personnel $me): JsonResponse
    {
        try {
            $content = $this->queries->findForDetail($contentId);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        /** @var SocialComment $fresh */
        $fresh = $content->comments->firstWhere('id', (int) $comment->getKey()) ?? $comment;
        $fresh->setRelation('content', $content);

        if (! $fresh->isReply()) {
            $fresh->setRelation('replies', $content->comments
                ->filter(fn (SocialComment $reply): bool => (int) $reply->parent_comment_id === (int) $fresh->getKey())
                ->values());
        }

        return response()->json([
            'comment' => $this->comments->comment($fresh, $me),
            'content' => $this->contents->detail($content, $me),
        ]);
    }

    /**
     * Dogrulama; mesajlar ve alan adlari lang `social_content.validation`
     * altindan gelir (cerceve varsayilani Ingilizcedir).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $messages = __('social_content.validation.messages');
        $attributes = __('social_content.validation.attributes');

        try {
            return $request->validate(
                $rules,
                is_array($messages) ? $messages : [],
                is_array($attributes) ? $attributes : [],
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $first = null;

            foreach ($errors as $lines) {
                foreach ((array) $lines as $line) {
                    $first ??= (string) $line;
                }
            }

            // `message` yalniz ilk hatanin Turkce metnidir (cercevenin Ingilizce eki olmadan).
            throw new HttpResponseException(response()->json([
                'message' => $first ?? (string) __('exceptions.generic'),
                'errors' => $errors,
                'code' => 'invalid_input',
            ], 422));
        }
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B31') && FeatureFlags::enabled('social_media.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }

    /** Is hatasi: `{ message, code }`; surum cakismasi 409, digerleri 422. */
    private function failure(AbstractException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->userMessage(),
            'code' => Str::snake(Str::beforeLast(class_basename($exception), 'Exception')),
        ], $exception instanceof StaleRecordException ? 409 : 422);
    }
}
