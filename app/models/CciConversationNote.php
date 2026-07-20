<?php

class CciConversationNote extends Model
{
	protected string $table = 'cci_conversacion_notas';

	public function byConversation(int $conversationId): array
	{
		$stmt = $this->db->prepare('SELECT n.*, COALESCE(u.nombre, "Sistema") AS usuario_nombre
			FROM cci_conversacion_notas n
			LEFT JOIN usuarios u ON u.id = n.usuario_id
			WHERE n.conversacion_id = :conversation_id
			ORDER BY n.id DESC');
		$stmt->execute(['conversation_id' => $conversationId]);
		return $stmt->fetchAll() ?: [];
	}
}
