<?php

declare(strict_types=1);

class GraphClient
{
    private array $graph;
    private $tokenService;

    public function __construct(array $graphConfig, $tokenService = null)
    {
        $this->graph = $graphConfig;
        $this->tokenService = $tokenService ?? new GraphTokenService($graphConfig);
    }

    public function get(string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
    {
        $baseUrl = rtrim((string) ($this->graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
        return $this->executeRequest('GET', $baseUrl . $path, $payload, $query, $extraHeaders);
    }

    public function post(string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
    {
        $baseUrl = rtrim((string) ($this->graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
        return $this->executeRequest('POST', $baseUrl . $path, $payload, $query, $extraHeaders);
    }

    public function patch(string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
    {
        $baseUrl = rtrim((string) ($this->graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
        return $this->executeRequest('PATCH', $baseUrl . $path, $payload, $query, $extraHeaders);
    }

    public function delete(string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
    {
        $baseUrl = rtrim((string) ($this->graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
        return $this->executeRequest('DELETE', $baseUrl . $path, $payload, $query, $extraHeaders);
    }

    public function request(string $method, string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
    {
        $baseUrl = rtrim((string) ($this->graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
        return $this->executeRequest($method, $baseUrl . $path, $payload, $query, $extraHeaders);
    }

    public function requestAbsolute(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
    {
        return $this->executeRequest($method, $url, $payload, [], $extraHeaders);
    }

    private function executeRequest(string $method, string $url, ?array $payload = null, array $query = [], array $extraHeaders = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'La extension cURL de PHP no esta habilitada.'];
        }

        $tokenResult = $this->tokenService->getAccessToken();
        if (!$tokenResult['ok']) {
            return ['ok' => false, 'error' => $tokenResult['error']];
        }

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $retryStatuses = [429, 503, 504];
        $attempts = 3;
        $lastError = 'Error desconocido en Microsoft Graph.';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'No se pudo inicializar cURL para Graph.'];
            }

            $headers = array_merge([
                'Authorization: Bearer ' . $tokenResult['access_token'],
                'Accept: application/json',
                'Content-Type: application/json',
            ], $extraHeaders);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => (int) ($this->graph['timeout'] ?? 30),
                CURLOPT_ENCODING => '',
            ]);

            if ($payload !== null) {
                $json = json_encode($payload);
                if ($json === false) {
                    curl_close($ch);
                    return ['ok' => false, 'error' => 'No se pudo serializar payload Graph.'];
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            }

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                $lastError = 'Error cURL Graph: ' . $curlError;
                if ($attempt < $attempts) {
                    sleep((int) pow(2, $attempt));
                    continue;
                }

                return ['ok' => false, 'error' => $lastError];
            }

            $body = null;
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'status' => $status, 'body' => $body];
            }

            $lastError = $this->extractGraphError($body, $status, $raw);
            if (!in_array($status, $retryStatuses, true) || $attempt >= $attempts) {
                return ['ok' => false, 'status' => $status, 'error' => $lastError];
            }

            sleep((int) pow(2, $attempt));
        }

        return ['ok' => false, 'error' => $lastError];
    }

    private function extractGraphError($body, int $status, string $raw): string
    {
        if (is_array($body)) {
            if (isset($body['error_description']) && is_string($body['error_description'])) {
                return trim($body['error_description']);
            }

            if (isset($body['error'])) {
                $errorNode = $body['error'];
                if (is_array($errorNode)) {
                    $code = trim((string) ($errorNode['code'] ?? ''));
                    $message = trim((string) ($errorNode['message'] ?? 'Error Graph no especificado.'));
                    return ($code !== '' ? $code . ': ' : '') . $message;
                }

                if (is_string($errorNode)) {
                    return trim($errorNode);
                }
            }
        }

        $fallback = trim($raw);
        if ($fallback === '') {
            $fallback = 'Error HTTP ' . $status . ' en Microsoft Graph.';
        }

        return $fallback;
    }
}
