<?php

declare(strict_types=1);

namespace Osmium\Services\Mailtrap\Models;

use Osmium\Core\Library\MailerInterface;

/**
 * Sends mail via Mailtrap's Email Sending API (JSON POST with a bearer API
 * token), either live or into a sandbox testing inbox. Plain curl, so there is
 * no SDK to install.
 *
 * Built by MailerFactory with the site config, which supplies the shared
 * From address and name; the token, mode and inbox come from this service's
 * own config.
 */
class MailtrapMailer implements MailerInterface
{
    private const LIVE_URL = 'https://send.api.mailtrap.io/api/send';
    private const SANDBOX_URL_TEMPLATE = 'https://sandbox.api.mailtrap.io/api/send/%s';

    public function __construct(private object $config) {}

    /**
     * @param array<int, array{email: string, name?: string}> $recipients
     * @return array{success: bool, error?: string}
     */
    public function send(array $recipients, string $subject, string $htmlBody): array
    {
        try {
            $this->postMessage($recipients, $subject, $htmlBody);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<int, array{email: string, name?: string}> $recipients
     */
    private function postMessage(array $recipients, string $subject, string $htmlBody): void
    {
        $mailtrap = MailtrapConfig::get();

        $notReady = !MailtrapConfig::isReady();
        if ($notReady) throw new \RuntimeException('Mailtrap is not configured: set the API token (and the inbox ID in sandbox mode) on the Mailtrap settings page.');

        $url = $mailtrap->mode === 'sandbox'
            ? \sprintf(self::SANDBOX_URL_TEMPLATE, \rawurlencode((string) $mailtrap->inboxId))
            : self::LIVE_URL;

        $email = $this->config->email;
        $from = ['email' => $email->fromAddress];
        $fromName = $email->fromName ?? '';
        if ($fromName !== '') $from['name'] = $fromName;

        $to = \array_map(
            function (array $recipient): array {
                $address = ['email' => $recipient['email']];
                if (isset($recipient['name'])) $address['name'] = $recipient['name'];
                return $address;
            },
            $recipients,
        );

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode([
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $htmlBody,
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $mailtrap->apiToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = \curl_exec($ch);
        $curlError = \curl_error($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($curlError) throw new \RuntimeException("Mailtrap send curl error: {$curlError}");

        $decoded = \json_decode(json: (string) $response, associative: true);
        $httpFailed = $httpCode < 200 || $httpCode >= 300;
        $reportedFailure = ($decoded['success'] ?? true) === false;
        if ($httpFailed || $reportedFailure) {
            $errors = $decoded['errors'] ?? null;
            $error = \is_array($errors) ? \implode('; ', \array_map('strval', $errors)) : ($response ?: "HTTP {$httpCode}");
            throw new \RuntimeException("Mailtrap send failed ({$httpCode}): {$error}");
        }
    }
}
