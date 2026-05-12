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

    public function search(array $filters = [], int $limit = 1000): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['tabla'])) {
            $where[] = 'tabla = :tabla';
            $params['tabla'] = $filters['tabla'];
        }

        if (!empty($filters['accion'])) {
            $where[] = 'accion = :accion';
            $params['accion'] = strtoupper($filters['accion']);
        }

        if (!empty($filters['usuario_id'])) {
            $where[] = 'usuario_id = :usuario_id';
            $params['usuario_id'] = (int) $filters['usuario_id'];
        }

        if (!empty($filters['fecha_desde'])) {
            $where[] = 'fecha >= :fecha_desde';
            $params['fecha_desde'] = $filters['fecha_desde'] . ' 00:00:00';
        }

        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'fecha <= :fecha_hasta';
            $params['fecha_hasta'] = $filters['fecha_hasta'] . ' 23:59:59';
        }

        $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table}{$whereSql} ORDER BY fecha DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
