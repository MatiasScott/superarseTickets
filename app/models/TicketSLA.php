<?php
// Modelo para SLA de tickets por prioridad
class TicketSLA extends Model
{
    protected string $table = 'ticket_sla';

    public function getAll(): array
    {
        $sql = "SELECT * FROM ticket_sla ORDER BY FIELD(prioridad, 'alta', 'media', 'baja')";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    public function getByPrioridad(string $prioridad): ?array
    {
        $sql = "SELECT * FROM ticket_sla WHERE prioridad = :prioridad LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['prioridad' => strtolower($prioridad)]);
        return $stmt->fetch() ?: null;
    }
}
