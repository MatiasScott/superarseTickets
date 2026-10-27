<?php

class MailTicketPolicy
{
	public static function startDate(array $account): ?string
	{
		$email = strtolower(trim((string) ($account['account_email'] ?? $account['email'] ?? '')));
		return $email === 'solucionesnexo@superarse.edu.ec' ? '2026-10-27T05:00:00Z' : null;
	}

	public static function acceptsDate(array $account, string $receivedAt): bool
	{
		$start = self::startDate($account);
		if ($start === null) {
			return true;
		}

		$timestamp = trim($receivedAt) !== '' ? strtotime($receivedAt) : false;
		if ($timestamp === false) {
			throw new RuntimeException('Fecha de recepcion invalida para el buzon de Comunicacion y Marketing.');
		}

		return $timestamp >= strtotime($start);
	}
}
