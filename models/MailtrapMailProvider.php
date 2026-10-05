<?php

declare(strict_types=1);

namespace Osmium\Services\Mailtrap\Models;

/**
 * Adapts Mailtrap to core's mail.providers hook (see ServiceHooks /
 * MailerFactory).
 */
class MailtrapMailProvider
{
    public const ID = 'mailtrap';

    /**
     * mail.providers - advertise Mailtrap and whether it can send now.
     */
    public static function provider(array $payload): array
    {
        return [
            'id' => self::ID,
            'label' => 'Mailtrap',
            'ready' => MailtrapConfig::isReady(),
            'settingsRoute' => 'settings/mailtrap/',
            'mailer' => MailtrapMailer::class,
        ];
    }
}
