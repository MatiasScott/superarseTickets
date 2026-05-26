<?php

declare(strict_types=1);

class MailSyncService
{
    public function runFastSync(?string $accountAlias = null): array
    {
        $controller = new CorreoController();
        $method = new ReflectionMethod('CorreoController', 'runTicketSync');
        $method->setAccessible(true);

        return (array) $method->invoke($controller, $accountAlias);
    }
}
