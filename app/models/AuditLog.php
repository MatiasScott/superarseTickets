<?php

class AuditLog extends Model
{
    protected string $table = 'auditoria';

    public function latest(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY fecha DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
