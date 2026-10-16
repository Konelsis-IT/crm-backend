<?php

declare(strict_types=1);

namespace App\Infrastructure\AiService;

use App\Exceptions\AiService\AiServiceAccessDeniedException;
use App\Exceptions\AiService\AiServiceLimitReachedException;
use App\Exceptions\AiService\AiServiceNotConfiguredException;
use App\Exceptions\AiService\AiServiceRequestRejectedException;
use App\Exceptions\AiService\AiServiceResultInvalidException;
use App\Exceptions\AiService\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

/**
 * Konelsis AI Service istemcisi (D-192; ai-service planlama kaydi 03, 27, 29).
 *
 * Her istek ai-service'in referans istemcisiyle (tools/crm-analysis-client.php)
 * ayni imzayi tasir: `Authorization: Bearer`, X-Client-Id, X-Timestamp,
 * X-Nonce, X-Content-SHA256 (ham govdenin SHA-256'si), X-Signature
 * ("v1=" + base64url HMAC-SHA256), Idempotency-Key ve X-Correlation-Id.
 * Kanonik metin: METHOD\nhedef\nzaman\nnonce\nhash\nidempotency (son satirdan
 * sonra LF yok). Token ve secret yalniz config('services.ai_service')'tan
 * okunur; loga, hataya ya da arayuze yazilmaz. Yeni paket yok (Laravel HTTP).
 */
final class AiServiceClient
{
    private const ANALYSIS_PATH = '/api/v1/analysis-requests';

    public function isConfigured(): bool
    {
        try {
            $this->settings();

            return true;
        } catch (AiServiceNotConfiguredException) {
            return false;
        }
    }

    /**
     * POST /api/v1/analysis-requests. Ayni idempotency anahtari ve ayni govdeyle
     * tekrar gonderim ai-service'te mevcut istegi dondurur.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> `data` (request_id, external_job_id, status, ...)
     */
    public function submitAnalysis(array $body, string $idempotencyKey): array
    {
        try {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw AiServiceRequestRejectedException::make(previous: $exception);
        }

        return $this->data($this->send('POST', self::ANALYSIS_PATH, $json, $idempotencyKey));
    }

    /**
     * GET /api/v1/analysis-requests/{id}: durum (accepted, queued, running, completed, failed).
     *
     * @return array<string, mixed>
     */
    public function analysisStatus(int $requestId): array
    {
        return $this->data($this->send('GET', self::ANALYSIS_PATH.'/'.$requestId, '', $this->readKey()));
    }

    /**
     * GET /api/v1/analysis-requests/{id}/result. Sonuc henuz yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function analysisResult(int $requestId): ?array
    {
        $response = $this->send('GET', self::ANALYSIS_PATH.'/'.$requestId.'/result', '', $this->readKey(), allowNotFound: true);

        return $response === null ? null : $this->data($response);
    }

    private function send(string $method, string $path, string $body, string $idempotencyKey, bool $allowNotFound = false): ?Response
    {
        $settings = $this->settings();
        $target = $settings['path_prefix'].$path;
        $timestamp = (string) time();
        $nonce = self::base64Url(random_bytes(24));
        $contentHash = hash('sha256', $body);
        $canonical = implode("\n", [$method, $target, $timestamp, $nonce, $contentHash, $idempotencyKey]);
        $signature = 'v1='.self::base64Url(hash_hmac('sha256', $canonical, $settings['secret'], true));

        try {
            $request = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$settings['token'],
                'X-Client-Id' => $settings['client_id'],
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
                'X-Content-SHA256' => $contentHash,
                'X-Signature' => $signature,
                'Idempotency-Key' => $idempotencyKey,
                'X-Correlation-Id' => bin2hex(random_bytes(16)),
            ])
                ->connectTimeout($settings['connect_timeout'])
                ->timeout($settings['timeout']);

            $response = $method === 'POST'
                ? $request->withBody($body, 'application/json')->post($settings['origin'].$target)
                : $request->get($settings['origin'].$target);
        } catch (ConnectionException $exception) {
            throw AiServiceUnavailableException::make(previous: $exception);
        } catch (Throwable $exception) {
            throw AiServiceUnavailableException::make(previous: $exception);
        }

        if ($response->successful()) {
            return $response;
        }

        $code = (string) ($response->json('error.code') ?? '');

        if ($allowNotFound && $response->status() === 404) {
            return null;
        }

        throw match (true) {
            $response->status() === 429 => AiServiceLimitReachedException::make(),
            in_array($response->status(), [401, 403], true) => AiServiceAccessDeniedException::make(),
            $response->status() === 409 && in_array($code, ['CAPABILITY_UNSUPPORTED', 'AGENT_INACTIVE', 'SERVICE_UNAVAILABLE'], true) => AiServiceUnavailableException::make(),
            $response->status() >= 500 => AiServiceUnavailableException::make(),
            default => AiServiceRequestRejectedException::make(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function data(?Response $response): array
    {
        $data = $response?->json('data');

        if (! is_array($data)) {
            throw AiServiceResultInvalidException::make();
        }

        return $data;
    }

    /**
     * @return array{origin: string, path_prefix: string, client_id: string, token: string, secret: string, timeout: int, connect_timeout: int}
     */
    private function settings(): array
    {
        $config = (array) config('services.ai_service', []);
        $baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        $parts = parse_url($baseUrl);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $clientId = trim((string) ($config['client_id'] ?? ''));
        $token = (string) ($config['token'] ?? '');
        $secret = (string) ($config['secret'] ?? '');

        $allowed = in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && ($scheme === 'https' || in_array($host, ['127.0.0.1', 'localhost', '::1', '[::1]'], true));

        if (! $allowed
            || preg_match('/\A[a-zA-Z0-9._-]{1,100}\z/D', $clientId) !== 1
            || preg_match('/\A[^\s]{32,512}\z/D', $token) !== 1
            || $secret === '') {
            throw AiServiceNotConfiguredException::make();
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return [
            'origin' => $scheme.'://'.$host.$port,
            'path_prefix' => rtrim((string) ($parts['path'] ?? ''), '/'),
            'client_id' => $clientId,
            'token' => $token,
            'secret' => $secret,
            'timeout' => max(5, (int) ($config['timeout'] ?? 60)),
            'connect_timeout' => max(1, (int) ($config['connect_timeout'] ?? 10)),
        ];
    }

    /** GET isteklerinde de imza bir idempotency anahtari ister; her okuma tek kullanimliktir. */
    private function readKey(): string
    {
        return 'crm-read-'.bin2hex(random_bytes(12));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
