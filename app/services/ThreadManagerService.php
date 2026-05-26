<?php

declare(strict_types=1);

class ThreadManagerService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findTicketIdByThread(string $conversationId = '', string $internetMessageId = '', ?int $subjectTicketId = null): ?int
    {
        $conversationId = trim($conversationId);
        if ($conversationId !== '') {
            $stmt = $this->db->prepare('SELECT ticket_id FROM mail_ticket_sync WHERE conversation_id = :conversation_id ORDER BY id DESC LIMIT 1');
            $stmt->execute(['conversation_id' => $conversationId]);
            $ticketId = (int) $stmt->fetchColumn();
            if ($ticketId > 0) {
                return $ticketId;
            }

            $stmtMsg = $this->db->prepare('SELECT ticket_id FROM ticket_mensajes WHERE conversation_id = :conversation_id ORDER BY id DESC LIMIT 1');
            $stmtMsg->execute(['conversation_id' => $conversationId]);
            $ticketId = (int) $stmtMsg->fetchColumn();
            if ($ticketId > 0) {
                return $ticketId;
            }
        }

        $internetMessageId = trim($internetMessageId);
        if ($internetMessageId !== '') {
            $stmt = $this->db->prepare('SELECT ticket_id FROM mail_ticket_sync WHERE internet_message_id = :internet_message_id OR message_id = :internet_message_id ORDER BY id DESC LIMIT 1');
            $stmt->execute(['internet_message_id' => $internetMessageId]);
            $ticketId = (int) $stmt->fetchColumn();
            if ($ticketId > 0) {
                return $ticketId;
            }

            $stmtMsg = $this->db->prepare('SELECT ticket_id FROM ticket_mensajes WHERE internet_message_id = :internet_message_id ORDER BY id DESC LIMIT 1');
            $stmtMsg->execute(['internet_message_id' => $internetMessageId]);
            $ticketId = (int) $stmtMsg->fetchColumn();
            if ($ticketId > 0) {
                return $ticketId;
            }
        }

        if ($subjectTicketId !== null && $subjectTicketId > 0) {
            $stmtTicket = $this->db->prepare('SELECT id FROM tickets WHERE id = :id LIMIT 1');
            $stmtTicket->execute(['id' => $subjectTicketId]);
            $existing = (int) $stmtTicket->fetchColumn();
            if ($existing > 0) {
                return $existing;
            }
        }

        return null;
    }
}
