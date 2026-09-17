<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Party\PartyService;
use BackedEnum;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateParty extends CreateRecord
{
    protected static string $resource = PartyResource::class;

    /**
     * Yeni taraf en az bir tiple acilir (D-95, 16 Eylul 2026 kullanici
     * karari): formdaki "Taraf tipi bilgileri" satirlari ve kuruma ait
     * iletisim satirlari (B28) PartyService icinde, taraf kaydiyla ayni
     * islemde yazilir. Her tip satiri "Taraf tipi" listesindeki pencereyle
     * ayni alanlari tasir.
     *
     * Not: coklu secim alanindaki secenekler enum nesnesi olarak gelir; bu
     * yuzden degerler kaydetmeden once metne cevrilir (onceki surumde bu
     * atlandigi icin tipler yazilmiyordu).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data['party_roles'] = $this->pullRoleRows($data);
        $data['communication_points'] = $this->pullChannelRows($data);

        try {
            return app(PartyService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['display_name' => $exception->userMessage()]);
        }
    }

    /**
     * Formdaki taraf tipi satirlarini ayirir ve enum degerlerini metne cevirir.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function pullRoleRows(array &$data): array
    {
        $rows = (array) ($data['party_roles'] ?? []);
        unset($data['party_roles']);

        $result = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = self::scalar($row['role_code'] ?? null);

            if ($code === null || $code === '') {
                continue;
            }

            $result[] = [
                'role_code' => $code,
                'status' => self::scalar($row['status'] ?? null) ?? 'active',
                'approved_by_personnel_id' => filled($row['approved_by_personnel_id'] ?? null) ? (int) $row['approved_by_personnel_id'] : null,
                'valid_from' => filled($row['valid_from'] ?? null) ? $row['valid_from'] : null,
                'valid_until' => filled($row['valid_until'] ?? null) ? $row['valid_until'] : null,
            ];
        }

        return $result;
    }

    /**
     * Formdaki kuruma ait iletisim satirlarini ayirir (B28); degeri bos
     * satirlar atlanir. Kanal turu enum nesnesi olarak gelebilir; serviste
     * metne cevrilir.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function pullChannelRows(array &$data): array
    {
        $rows = (array) ($data['communication_points'] ?? []);
        unset($data['communication_points']);

        return array_values(array_filter(
            $rows,
            static fn ($row): bool => is_array($row) && filled($row['value'] ?? null),
        ));
    }

    private static function scalar(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
