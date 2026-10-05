<?php

declare(strict_types=1);

namespace Osmium\Services\Mailtrap\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Mailtrap\Models\MailtrapConfig;

/**
 * Mailtrap settings controller - full-page form POST/redirect, same shape as
 * TurnstileController.
 *
 * Routes:
 *   - index() → /admin/settings/mailtrap/  (GET shows the form, POST saves it)
 */
class MailtrapController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/mailtrap.json.php';

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['mailtrap'] = (array) MailtrapConfig::get();
        $this->data['admin']['mailtrapModes'] = MailtrapConfig::MODES;
        $this->data['admin']['settingsSaved'] = $_SESSION['mailtrap_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['mailtrap_settings_error'] ?? false;
        unset($_SESSION['mailtrap_settings_saved'], $_SESSION['mailtrap_settings_error']);

        $this->setView('mailtrap/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['mailtrap_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/mailtrap/');
        }

        $mode = $_POST['mode'] ?? 'live';
        $modeValid = \array_key_exists($mode, MailtrapConfig::MODES);
        if (!$modeValid) $mode = 'live';

        $inboxId = \trim($_POST['inbox_id'] ?? '');
        $inboxIdValid = $inboxId === '' || \ctype_digit($inboxId);
        if (!$inboxIdValid) {
            $_SESSION['mailtrap_settings_error'] = 'The inbox ID is the number in your Mailtrap inbox URL.';
            $this->redirect('settings/mailtrap/');
        }

        $postedToken = \trim($_POST['api_token'] ?? '');
        $apiToken = $postedToken === '' ? (string) (MailtrapConfig::get()->apiToken ?? '') : $postedToken; // Blank keeps the stored secret

        $this->saveConfig($apiToken, $mode, $inboxId);

        $this->admin->model->changelog->log(
            description: 'Updated Mailtrap settings',
            recordType: 'settings',
        );

        MailtrapConfig::clearCache();

        $_SESSION['mailtrap_settings_saved'] = true;
        $this->redirect('settings/mailtrap/');
    }

    private function saveConfig(string $apiToken, string $mode, string $inboxId): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $newJson = \json_encode(
            value: ['mailtrap' => [
                'apiToken' => $apiToken,
                'mode' => $mode,
                'inboxId' => $inboxId,
            ]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, "<?php exit(); ?>\n" . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
