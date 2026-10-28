<?php

class MailTicketPolicy
{
	public static function startDate(array $account): ?string
	{
		$email = strtolower(trim((string) (
			$account['account_email'] ?? $account['email'] ?? ''
		)));

		return $email === 'solucionesnexo@superarse.edu.ec'
			? '2026-10-27T05:00:00Z'
			: null;
	}

	public static function acceptsDate(array $account, string $receivedAt): bool
	{
		$start = self::startDate($account);

		if ($start === null) {
			return true;
		}

		$receivedAt = trim($receivedAt);

		if ($receivedAt === '') {
			error_log(
				'MailTicketPolicy: fecha de recepcion vacia; mensaje omitido.'
			);
			return false;
		}

		$timestamp = strtotime($receivedAt);

		if ($timestamp === false) {
			error_log(
				'MailTicketPolicy: fecha de recepcion invalida; mensaje omitido.'
			);
			return false;
		}

		$startTimestamp = strtotime($start);

		if ($startTimestamp === false) {
			throw new RuntimeException(
				'Configuracion de fecha inicial invalida en MailTicketPolicy.'
			);
		}

		return $timestamp >= $startTimestamp;
	}
}
