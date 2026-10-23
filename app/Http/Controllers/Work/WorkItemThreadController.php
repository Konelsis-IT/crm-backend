<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Exceptions\AbstractException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Models\Report\WorkItemFile;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\WorkItemPresenter;
use App\Services\Report\WorkItemService;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * Isin cevaplari ve belgeleri (D-241; kullanici 23 Ekim 2026: "Ve her personel
 * duzeltme yapamaz. Ise karsi cevap yazilabilir olmasi lazim. Belge
 * yuklenebilir olmasi lazim. Bu ozelliklerde gelsin yine ayni arayuzde temiz
 * sekilde"). Akilli is penceresinin okuma gorunumu kullanir. Isi gorebilen
 * cevap yazar ve belge yukler; belge baglantisini isi duzenleyebilen kaldirir
 * (WorkItemFilePolicy). Yazma WorkItemService'te, bicim WorkItemPresenter'da.
 */
final class WorkItemThreadController extends Controller
{
    /** Bir yuklemede en fazla belge (Filament belge alaniyla ayni). */
    private const MAX_FILES = 20;

    public function __construct(
        private readonly WorkItemPresenter $presenter,
        private readonly WorkItemService $service,
    ) {}

    public function show(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->viewer($request, $item);

        return response()->json($this->payload($item, $me));
    }

    public function reply(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->viewer($request, $item);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], [], ['body' => __('work_item.ui.qa_th_reply_label')]);

        return $this->respond(function () use ($item, $me, $data): array {
            $this->service->reply($item, (string) $data['body']);

            return $this->payload($item, $me);
        });
    }

    public function upload(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->viewer($request, $item);
        $request->validate([
            'files' => ['required', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:'.UploadLimits::documentMaxKb()],
        ], [], ['files' => __('work_item.ui.qa_th_files'), 'files.*' => __('work_item.ui.qa_th_files')]);

        $uploads = [];

        foreach ((array) $request->file('files', []) as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $file->store('document-uploads-tmp', 'local');

                if (is_string($path) && $path !== '') {
                    $uploads[$path] = $file->getClientOriginalName();
                }
            }
        }

        return $this->respond(fn (): array => [
            'added' => $this->service->addFiles($item, $uploads),
            ...$this->payload($item, $me),
        ]);
    }

    public function removeFile(Request $request, WorkItemFile $file): JsonResponse
    {
        $item = $file->workItem;
        abort_unless($item instanceof WorkItem, 404);

        $me = $this->viewer($request, $item);
        Gate::forUser($me)->authorize('delete', $file);

        return $this->respond(function () use ($file, $item, $me): array {
            $this->service->removeFile($file);

            return $this->payload($item, $me);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WorkItem $item, Personnel $me): array
    {
        return [
            ...$this->presenter->thread($item, $me),
            'can' => ['reply' => true, 'upload' => true],
            'max_upload' => UploadLimits::readable(UploadLimits::documentMaxKb()),
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (AbstractException $exception) {
            return response()->json(['message' => $exception->userMessage(), 'code' => 'business_rule'], 422);
        }
    }

    private function viewer(Request $request, WorkItem $item): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B57'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);
        Gate::forUser($user)->authorize('view', $item);

        return $user;
    }
}
