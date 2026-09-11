<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Document\DocumentService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokuman olusturma (D-75): bilgiler + belgenin asli (dosya ya da sistemde
 * yazilan govde) tek adimda; ilk revizyon otomatik acilir, ardindan detay
 * arayuzune gecilir.
 */
class CreateDocumentRecord extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    public function getSubheading(): ?string
    {
        return __('document.help.create_intro');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['file_temp_path'] = $data['file'] ?? null;
        unset($data['file']);

        try {
            return app(DocumentService::class)->createWithInitialRevision($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
