<?php

declare(strict_types=1);

class JobService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->connection();
        $this->ensureTables();
    }

    public function create(string $tipo, array $payload = []): int
    {
        $stmt = $this->db->prepare('INSERT INTO jobs (tipo, estado, inicio, mensaje, payload) VALUES (:tipo, :estado, NOW(), :mensaje, :payload)');
        $stmt->execute([
            'tipo' => substr($tipo, 0, 100),
            'estado' => 'pendiente',
            'mensaje' => 'Encolado',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markRunning(int $jobId): void
    {
        $this->updateStatus($jobId, 'procesando', 'Procesando', false);
    }

    public function markDone(int $jobId, array $result = []): void
    {
        $this->updateStatus($jobId, 'completado', 'Completado', true, $result);
    }

    public function markError(int $jobId, string $message, array $result = []): void
    {
        $this->updateStatus($jobId, 'error', $message, true, $result);
    }

    public function addDetail(int $jobId, ?string $registroId, string $descripcion): void
    {
        if ($jobId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO jobs_detalles (job_id, registro_id, descripcion, created_at) VALUES (:job_id, :registro_id, :descripcion, NOW())');
        $stmt->execute([
            'job_id' => $jobId,
            'registro_id' => $registroId !== null ? substr($registroId, 0, 120) : null,
            'descripcion' => substr($descripcion, 0, 1000),
        ]);
    }

    private function updateStatus(int $jobId, string $estado, string $mensaje, bool $finish = false, array $result = []): void
    {
        if ($jobId <= 0) {
            return;
        }

        $sql = 'UPDATE jobs SET estado = :estado, mensaje = :mensaje, resultado = :resultado';
        if ($finish) {
            $sql .= ', fin = NOW()';
        }
        $sql .= ' WHERE id = :id LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $jobId,
            'estado' => substr($estado, 0, 20),
            'mensaje' => substr($mensaje, 0, 500),
            'resultado' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function ensureTables(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(100) NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            inicio DATETIME NOT NULL,
            fin DATETIME NULL,
            mensaje VARCHAR(500) NULL,
            payload JSON NULL,
            resultado JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jobs_tipo_estado (tipo, estado),
            INDEX idx_jobs_inicio (inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS jobs_detalles (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT NOT NULL,
            registro_id VARCHAR(120) NULL,
            descripcion VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jobs_detalles_job (job_id),
            CONSTRAINT fk_jobs_detalles_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
