<?php

class ConvenioTarea extends Model
{
    protected string $table = 'convenio_tareas';

    public function listByConvenio(int $convenioId): array
    {
        $sql = "SELECT t.*, tt.nombre AS tipo_tarea_nombre, r.nombre AS resultado_nombre,
                up.nombre AS propietario_nombre, uc.nombre AS created_by_nombre,
                GROUP_CONCAT(DISTINCT ur.nombre ORDER BY ur.nombre SEPARATOR ', ') AS relacionados,
                GROUP_CONCAT(DISTINCT uc2.nombre ORDER BY uc2.nombre SEPARATOR ', ') AS colaboradores,
                GROUP_CONCAT(DISTINCT tr.usuario_id ORDER BY tr.usuario_id SEPARATOR ',') AS relacionados_ids,
                GROUP_CONCAT(DISTINCT tc.usuario_id ORDER BY tc.usuario_id SEPARATOR ',') AS colaboradores_ids
            FROM convenio_tareas t
            LEFT JOIN tipo_tarea_convenios tt ON tt.id = t.tipo_tarea_id
            LEFT JOIN resultados r ON r.id = t.resultado_id
            LEFT JOIN usuarios up ON up.id = t.propietario_id
            LEFT JOIN usuarios uc ON uc.id = t.created_by
            LEFT JOIN convenio_tarea_relacionados tr ON tr.tarea_id = t.id
            LEFT JOIN usuarios ur ON ur.id = tr.usuario_id
            LEFT JOIN convenio_tarea_colaboradores tc ON tc.tarea_id = t.id
            LEFT JOIN usuarios uc2 ON uc2.id = tc.usuario_id
            WHERE t.convenio_id = :convenio_id
            GROUP BY t.id
            ORDER BY t.completado ASC, t.fecha_vencimiento ASC, t.hora_vencimiento ASC, t.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['convenio_id' => $convenioId]);
        return $stmt->fetchAll() ?: [];
    }

    public function updateParticipantes(int $tareaId, int $convenioId, array $relacionados = [], array $colaboradores = []): bool
    {
        $check = $this->db->prepare("SELECT id FROM convenio_tareas WHERE id = :id AND convenio_id = :convenio_id LIMIT 1");
        $check->execute([
            'id' => $tareaId,
            'convenio_id' => $convenioId,
        ]);

        if (!$check->fetch()) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $deleteRel = $this->db->prepare("DELETE FROM convenio_tarea_relacionados WHERE tarea_id = :tarea_id");
            $deleteCol = $this->db->prepare("DELETE FROM convenio_tarea_colaboradores WHERE tarea_id = :tarea_id");

            $deleteRel->execute(['tarea_id' => $tareaId]);
            $deleteCol->execute(['tarea_id' => $tareaId]);

            if (!empty($relacionados)) {
                $insertRel = $this->db->prepare("INSERT INTO convenio_tarea_relacionados (tarea_id, usuario_id) VALUES (:tarea_id, :usuario_id)");
                foreach ($relacionados as $usuarioId) {
                    $insertRel->execute([
                        'tarea_id' => $tareaId,
                        'usuario_id' => (int) $usuarioId,
                    ]);
                }
            }

            if (!empty($colaboradores)) {
                $insertCol = $this->db->prepare("INSERT INTO convenio_tarea_colaboradores (tarea_id, usuario_id) VALUES (:tarea_id, :usuario_id)");
                foreach ($colaboradores as $usuarioId) {
                    $insertCol->execute([
                        'tarea_id' => $tareaId,
                        'usuario_id' => (int) $usuarioId,
                    ]);
                }
            }

            $touch = $this->db->prepare("UPDATE convenio_tareas SET updated_at = NOW() WHERE id = :id");
            $touch->execute(['id' => $tareaId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function createTarea(array $data, array $relacionados = [], array $colaboradores = []): int
    {
        $this->db->beginTransaction();
        try {
            $taskId = $this->create($data);

            if (!empty($relacionados)) {
                $stmt = $this->db->prepare("INSERT INTO convenio_tarea_relacionados (tarea_id, usuario_id) VALUES (:tarea_id, :usuario_id)");
                foreach ($relacionados as $usuarioId) {
                    $stmt->execute([
                        'tarea_id' => $taskId,
                        'usuario_id' => (int) $usuarioId,
                    ]);
                }
            }

            if (!empty($colaboradores)) {
                $stmt = $this->db->prepare("INSERT INTO convenio_tarea_colaboradores (tarea_id, usuario_id) VALUES (:tarea_id, :usuario_id)");
                foreach ($colaboradores as $usuarioId) {
                    $stmt->execute([
                        'tarea_id' => $taskId,
                        'usuario_id' => (int) $usuarioId,
                    ]);
                }
            }

            $this->db->commit();
            return $taskId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateEstado(int $id, string $estado, int $completado): bool
    {
        return $this->update($id, [
            'estado' => $estado,
            'completado' => $completado,
        ]);
    }

    public function updateResultado(int $id, ?int $resultadoId): bool
    {
        return $this->update($id, [
            'resultado_id' => $resultadoId,
        ]);
    }

    public function activeTiposTarea(): array
    {
        $stmt = $this->db->query("SELECT id, nombre FROM tipo_tarea_convenios WHERE estado = 'activo' ORDER BY orden ASC, nombre ASC");
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function activeResultados(): array
    {
        $stmt = $this->db->query("SELECT id, nombre FROM resultados WHERE estado = 'activo' ORDER BY orden ASC, nombre ASC");
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function activeUsuarios(): array
    {
        $stmt = $this->db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre ASC");
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }
}
