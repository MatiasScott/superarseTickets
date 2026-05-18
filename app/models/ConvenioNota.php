<?php

class ConvenioNota extends Model
{
    protected string $table = 'convenio_notas';

    public function listByConvenio(int $convenioId): array
    {
        $sql = "SELECT n.*, u.nombre AS usuario_nombre
            FROM convenio_notas n
            LEFT JOIN usuarios u ON u.id = n.usuario_id
            WHERE n.convenio_id = :convenio_id
            ORDER BY n.created_at DESC, n.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['convenio_id' => $convenioId]);
        return $stmt->fetchAll() ?: [];
    }

    public function createNota(int $convenioId, int $usuarioId, string $nota): int
    {
        return $this->create([
            'convenio_id' => $convenioId,
            'usuario_id' => $usuarioId,
            'nota' => trim($nota),
        ]);
    }
}
