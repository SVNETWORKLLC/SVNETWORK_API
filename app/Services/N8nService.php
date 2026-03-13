<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class N8nService
{
    protected ?string $defaultWebhookUrl;

    public function __construct(?string $defaultWebhookUrl = null)
    {
        $this->defaultWebhookUrl = $defaultWebhookUrl
            ?? config('services.n8n.webhook_url_search', env('N8N_WEBHOOK_URL_SEARCH'));
    }

    public function send(array $payload): array
    {
        $url = $this->defaultWebhookUrl;

        if (!$url) {
            return [
                'success' => false,
                'status' => null,
                'message' => 'N8N webhook URL is not configured.',
                'data' => null,
            ];
        }

        try {
            $response = Http::timeout((int) config('services.n8n.timeout', 15))
                ->withHeaders([
                    'Authorization' => config('services.n8n.credentials', env('N8N_CREDENTIALS')),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            $data = $response->json();

            if (is_array($data) && isset($data['output']) && is_string($data['output'])) {
                $decodedOutput = json_decode($data['output'], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['output'] = $decodedOutput;
                }
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'N8N webhook sent successfully.' : 'N8N webhook request failed.',
                'data' => $data ?? $response->body(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'status' => null,
                'message' => $exception->getMessage(),
                'data' => null,
            ];
        }
    }
}
