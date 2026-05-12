<?php
// Modelo para grupos
class Grupo extends Model
{
    protected string $table = 'ticket_grupos';

    public function getUsuarios(int $grupoId): array
    {
        $sql = "SELECT u.* FROM usuarios u
                INNER JOIN usuario_grupos ug ON ug.usuario_id = u.id
                WHERE ug.grupo_id = :grupo_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['grupo_id' => $grupoId]);
        return $stmt->fetchAll() ?: [];
    }

    public function allGrupos(): array
    {
        $sql = "SELECT * FROM ticket_grupos ORDER BY nombre";
        return $this->db->query($sql)->fetchAll() ?: [];
    }
}
