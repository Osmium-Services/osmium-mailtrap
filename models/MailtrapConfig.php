<?php

declare(strict_types=1);

namespace Osmium\Services\Mailtrap\Models;

/**
 * Mailtrap configuration.
 *
 * File-based config (app/config/services/mailtrap.json.php), matching the
 * Xero/Stripe/PayPal/Turnstile convention. The From address and name are not
 * kept here: they stay on the core Email settings page, shared by every mail
 * provider.
 *
 * mode "live" sends for real through Mailtrap's Email Sending API; mode
 * "sandbox" delivers into a Mailtrap testing inbox instead, so nothing
 * reaches a real recipient.
 */
class MailtrapConfig
{
    public const MODES = [
        'live' => 'Live sending',
        'sandbox' => 'Sandbox (test inbox)',
    ];

    private static ?object $config = null;
    private static string $configPath = 'app/config/services/mailtrap.json.php';

    public static function get(): object
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        $configFile = self::$configPath;

        $configExists = \file_exists($configFile);
        if (!$configExists) {
            self::$config = self::defaults();
            return self::$config;
        }

        $content = \file_get_contents($configFile);
        $jsonStart = \strpos(haystack: $content, needle: '{');

        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) {
            self::$config = self::defaults();
            return self::$config;
        }

        $json = \substr(string: $content, offset: $jsonStart);
        $decoded = \json_decode($json);

        self::$config = (object) \array_merge((array) self::defaults(), (array) ($decoded->mailtrap ?? []));

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    /**
     * Can mail be sent right now - an API token is set, plus an inbox ID in sandbox mode.
     */
    public static function isReady(): bool
    {
        $config = self::get();

        $hasToken = ($config->apiToken ?? '') !== '';
        if (!$hasToken) return false;

        $needsInbox = ($config->mode ?? 'live') === 'sandbox';

        return !$needsInbox || ($config->inboxId ?? '') !== '';
    }

    private static function defaults(): object
    {
        return (object) [
            'apiToken' => '',
            'mode' => 'live',
            'inboxId' => '',
        ];
    }
}
