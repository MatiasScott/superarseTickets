<?php

declare(strict_types=1);

class GraphTokenService
{
    private array $graph;
    private string $cacheKey;

    public function __construct(array $graphConfig)
    {
        $this->graph = $graphConfig;
        $this->cacheKey = $this->buildCacheKey();
    }

    public function getAccessToken(): array
    {
        if ((string) env('APP_DEBUG', 'true') === 'true') {
            error_log('Solicitando token Graph');
        }

        $tenantId = trim((string) ($this->graph['tenant_id'] ?? ''));
        $clientId = trim((string) ($this->graph['client_id'] ?? ''));
        $clientSecret = trim((string) ($this->graph['client_secret'] ?? ''));

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
            return [
                'ok' => false,
                'error' => 'Faltan GRAPH_TENANT_ID, GRAPH_CLIENT_ID o GRAPH_CLIENT_SECRET en .env.',
            ];
        }

        $cached = $this->readCachedToken();
        if ($cached !== null) {
            return [
                'ok' => true,
                'access_token' => $cached['access_token'],
            ];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'La extension cURL de PHP no esta habilitada.'];
        }

        $tokenUrl = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
        $postData = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
        ]);

        $ch = curl_init($tokenUrl);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'No se pudo inicializar cURL para token Graph.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => (int) ($this->graph['timeout'] ?? 30),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'Error cURL token Graph: ' . $curlError];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Respuesta invalida de token Graph.'];
        }

        if ($status < 200 || $status >= 300 || empty($decoded['access_token'])) {
            $error = $this->extractGraphError($decoded, $status, $raw);
            return ['ok' => false, 'error' => $error];
        }

        $expiresIn = (int) ($decoded['expires_in'] ?? 3600);
        $accessToken = (string) $decoded['access_token'];
        $expiresAt = time() + max(60, $expiresIn - 60);
        $this->storeCachedToken($accessToken, $expiresAt);

        return ['ok' => true, 'access_token' => $accessToken];
    }

    private function buildCacheKey(): string
    {
        $tenantId = trim((string) ($this->graph['tenant_id'] ?? ''));
        $clientId = trim((string) ($this->graph['client_id'] ?? ''));
        return 'graph:' . sha1($tenantId . '|' . $clientId);
    }

    private function readCachedToken(): ?array
    {
        $now = time();

        if (function_exists('apcu_fetch')) {
            $success = false;
            $cached = apcu_fetch($this->cacheKey, $success);
            if ($success && is_array($cached)) {
                $expiresAt = (int) ($cached['expires_at'] ?? 0);
                $token = trim((string) ($cached['access_token'] ?? ''));
                if ($token !== '' && $expiresAt > $now + 30) {
                    return [
                        'access_token' => $token,
                        'expires_at' => $expiresAt,
                    ];
                }
            }
        }

        $dbRow = $this->readTokenFromDatabase();
        if ($dbRow !== null) {
            $expiresAt = (int) ($dbRow['expires_at'] ?? 0);
            $token = trim((string) ($dbRow['access_token'] ?? ''));
            if ($token !== '' && $expiresAt > $now + 30) {
                if (function_exists('apcu_store')) {
                    @apcu_store($this->cacheKey, [
                        'access_token' => $token,
                        'expires_at' => $expiresAt,
                    ], max(60, $expiresAt - $now));
                }

                return [
                    'access_token' => $token,
                    'expires_at' => $expiresAt,
                ];
            }
        }

        return null;
    }

    private function storeCachedToken(string $accessToken, int $expiresAt): void
    {
        if ($accessToken === '') {
            return;
        }

        if (function_exists('apcu_store')) {
            @apcu_store($this->cacheKey, [
                'access_token' => $accessToken,
                'expires_at' => $expiresAt,
            ], max(60, $expiresAt - time()));
        }

        $this->storeTokenInDatabase($accessToken, $expiresAt);
    }

    private function ensureTable(): void
    {
        $db = Database::getInstance()->connection();
        $db->exec("CREATE TABLE IF NOT EXISTS graph_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token_key VARCHAR(120) NOT NULL,
            access_token LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_graph_tokens_key (token_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function readTokenFromDatabase(): ?array
    {
        try {
            $this->ensureTable();
            $db = Database::getInstance()->connection();
            $stmt = $db->prepare('SELECT access_token, UNIX_TIMESTAMP(expires_at) AS expires_at FROM graph_tokens WHERE token_key = :token_key LIMIT 1');
            $stmt->execute(['token_key' => $this->cacheKey]);
            $row = $stmt->fetch();

            if (!is_array($row)) {
                return null;
            }

            return $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function storeTokenInDatabase(string $accessToken, int $expiresAt): void
    {
        try {
            $this->ensureTable();
            $db = Database::getInstance()->connection();
            $stmt = $db->prepare('INSERT INTO graph_tokens (token_key, access_token, expires_at, created_at, updated_at) VALUES (:token_key, :access_token, FROM_UNIXTIME(:expires_at), NOW(), NOW()) ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), expires_at = VALUES(expires_at), updated_at = NOW()');
            $stmt->execute([
                'token_key' => $this->cacheKey,
                'access_token' => $accessToken,
                'expires_at' => $expiresAt,
            ]);
        } catch (Throwable $e) {
        }
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
