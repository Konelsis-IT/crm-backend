<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\DuplicateRecordException;
use App\Models\SocialMedia\SocialCategory;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Lang;

/**
 * Icerik kategorisi servisi (B31, D-106).
 *
 * Kategori adi tektir (A7): ayni adla ikinci kayit DuplicateRecordException
 * ile reddedilir; es zamanli iki istekte veritabani tekil indeksi de ayni
 * hataya cevrilir. Renk sekiz palet adindan biridir. Kategori silinmez,
 * pasife alinir; bu yuzden delete() cagrilmaz.
 *
 * Hareket kaydinda ham deger yerine etiket yazilir (durum, renk).
 */
final class SocialCategoryService extends AbstractService
{
    protected string $model = SocialCategory::class;

    protected string $orderBy = 'sort_order';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $data = $this->normalize($data, null);

        try {
            return $this->transactions->run(function () use ($data): Model {
                $this->assertUniqueName((string) $data['name'], null);

                if (! array_key_exists('sort_order', $data)) {
                    $data['sort_order'] = $this->nextSortOrder();
                }

                return parent::create($data);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateRecordException::make([], $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        try {
            return $this->transactions->run(function () use ($record, $data): Model {
                /** @var SocialCategory $category */
                $category = $this->lockForUpdate($record);
                $data = $this->normalize($data, $category);

                if (array_key_exists('name', $data)) {
                    $this->assertUniqueName((string) $data['name'], (int) $category->getKey());
                }

                $category->fill($this->prepare($data, $category));
                $changes = $this->saveWithoutVersion($category);

                if ($changes !== []) {
                    $this->recordActivity($category, 'updated', $this->labelled($changes));
                }

                return $category;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateRecordException::make([], $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'name' => $record->getAttribute('name'),
            'color' => $this->display('color', $record->getAttribute('color')),
            'status' => $this->display('status', $record->getAttribute('status')),
        ];
    }

    /**
     * Gelen veriyi kolonlara indirger: ad kirpilir, renk palete, durum
     * aktif / pasif degerine sabitlenir. Guncellemede gonderilmeyen alan
     * oldugu gibi kalir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?SocialCategory $record): array
    {
        $clean = [];

        if (array_key_exists('name', $data) || $record === null) {
            $clean['name'] = trim((string) ($data['name'] ?? ''));
        }

        if (array_key_exists('color', $data) || $record === null) {
            $color = strtolower(trim((string) ($data['color'] ?? '')));
            $clean['color'] = in_array($color, SocialCategory::COLORS, true)
                ? $color
                : ($record?->color ?? SocialCategory::DEFAULT_COLOR);
        }

        if (filled($data['status'] ?? null)) {
            $status = $data['status'] instanceof ActiveStatus ? $data['status'] : ActiveStatus::tryFrom((string) $data['status']);

            if ($status !== null) {
                $clean['status'] = $status->value;
            }
        } elseif ($record === null) {
            $clean['status'] = ActiveStatus::Active->value;
        }

        if (filled($data['sort_order'] ?? null)) {
            $clean['sort_order'] = max(0, min(65535, (int) $data['sort_order']));
        }

        return $clean;
    }

    private function assertUniqueName(string $name, ?int $exceptId): void
    {
        $exists = SocialCategory::query()
            ->where('name', $name)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw DuplicateRecordException::make();
        }
    }

    private function nextSortOrder(): int
    {
        return min(65535, ((int) SocialCategory::query()->max('sort_order')) + 1);
    }

    /**
     * @param  array<string, array{onceki: mixed, yeni: mixed}>  $changes
     * @return array<string, array{onceki: mixed, yeni: mixed}>
     */
    private function labelled(array $changes): array
    {
        foreach ($changes as $column => $pair) {
            $changes[$column] = [
                'onceki' => $this->display((string) $column, $pair['onceki'] ?? null),
                'yeni' => $this->display((string) $column, $pair['yeni'] ?? null),
            ];
        }

        return $changes;
    }

    /** Hareket kaydinda gosterilecek deger: durum ve renk etiketle yazilir. */
    private function display(string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($column === 'status') {
            $status = $value instanceof ActiveStatus ? $value : ActiveStatus::tryFrom((string) $value);

            return $status?->getLabel() ?? (string) $value;
        }

        if ($column === 'color') {
            $key = 'social_content.colors.'.(string) $value;

            return Lang::has($key) ? (string) __($key) : (string) $value;
        }

        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
