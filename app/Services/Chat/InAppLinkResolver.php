<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Personnel\Personnel;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sohbette paylasilan baglantinin uygulama ici olup olmadigini ve okuyanin
 * ona erisip erisemeyecegini cozer (D-83: "yetkisi yoksa yetkisiz erisim
 * arayuzu gorulmeli"). Panel kaynagi ise Policy (canView/canEdit), panel
 * sayfasi ise canAccess() sorulur. Baglanti tiklandiginda sunucu zaten
 * 403 sayfasini gosterir; bu cozumleme mesajda kilit rozeti icindir.
 */
final class InAppLinkResolver
{
    /** @var array<string, array{in_app: bool, accessible: ?bool, label: ?string}> */
    private array $memo = [];

    /**
     * @return array{in_app: bool, accessible: ?bool, label: ?string}
     */
    public function resolve(string $url, Personnel $viewer): array
    {
        $cacheKey = $viewer->getKey().'|'.$url;

        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        return $this->memo[$cacheKey] = $this->doResolve($url, $viewer);
    }

    public function isInApp(string $url): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === '') {
            return str_starts_with($url, '/');
        }

        $requestHost = request()?->getHost();

        return strcasecmp((string) $host, (string) $appHost) === 0
            || ($requestHost !== null && strcasecmp((string) $host, $requestHost) === 0);
    }

    /**
     * @return array{in_app: bool, accessible: ?bool, label: ?string}
     */
    private function doResolve(string $url, Personnel $viewer): array
    {
        if (! $this->isInApp($url)) {
            return ['in_app' => false, 'accessible' => null, 'label' => null];
        }

        try {
            $route = Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return ['in_app' => true, 'accessible' => false, 'label' => null];
        }

        $name = (string) $route->getName();
        $panel = Filament::getPanel('admin');
        $prefix = 'filament.'.$panel->getId().'.';

        if (! str_starts_with($name, $prefix)) {
            return ['in_app' => true, 'accessible' => true, 'label' => null];
        }

        $previous = Auth::user();

        try {
            Auth::setUser($viewer);

            foreach ($panel->getResources() as $resource) {
                /** @var class-string<Resource> $resource */
                $base = $resource::getRouteBaseName($panel).'.';

                if (! str_starts_with($name, $base)) {
                    continue;
                }

                $page = Str::after($name, $base);
                $recordKey = $route->parameter('record');
                $record = $recordKey !== null ? $resource::resolveRecordRouteBinding($recordKey) : null;
                $label = $resource::getModelLabel();

                if ($record !== null) {
                    $title = $resource::getRecordTitle($record);
                    $label = filled($title) ? (string) $title : $label;
                }

                $accessible = match (true) {
                    $recordKey !== null && $record === null => false,
                    $page === 'view' && $record !== null => $resource::canView($record),
                    $page === 'edit' && $record !== null => $resource::canEdit($record),
                    $page === 'create' => $resource::canCreate(),
                    $record !== null => $resource::canView($record),
                    default => $resource::canViewAny(),
                };

                return ['in_app' => true, 'accessible' => (bool) $accessible, 'label' => $label];
            }

            foreach ($panel->getPages() as $pageClass) {
                /** @var class-string<Page> $pageClass */
                if ($pageClass::getRouteName($panel) !== $name) {
                    continue;
                }

                return ['in_app' => true, 'accessible' => (bool) $pageClass::canAccess(), 'label' => $pageClass::getNavigationLabel()];
            }
        } catch (Throwable) {
            return ['in_app' => true, 'accessible' => false, 'label' => null];
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            }
        }

        return ['in_app' => true, 'accessible' => true, 'label' => null];
    }
}
