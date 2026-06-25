<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AdminMailUiTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    private function render(array $overrides = []): string
    {
        $defaults = [
            'title'  => 'Settings',
            'csrf'   => 'test-csrf',
            'values' => [
                'mail_driver'   => 'resend',
                'mail_backup'   => 'none',
                'from_email'    => 'no-reply@example.com',
                'from_name'     => 'Crush',
            ],
            'keys' => [
                'mail_driver', 'mail_backup', 'from_email', 'from_name',
                'resend_api_key',
                'mailjet_api_key', 'mailjet_secret_key',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption',
                'telegram_bot_token', 'telegram_chat_id',
                'google_client_id', 'google_client_secret', 'google_redirect_uri',
                'invite_expiry_days',
            ],
        ];
        return $this->view()->render('admin/settings', array_merge($defaults, $overrides));
    }

    // --- Section headings (must remain for AdminRedesignTest parity) ---

    public function test_page_has_required_data_attribute(): void
    {
        $this->assertStringContainsString('data-admin-page="settings"', $this->render());
    }

    public function test_page_has_app_configuration_kicker(): void
    {
        $this->assertStringContainsString('App configuration', $this->render());
    }

    public function test_page_has_mail_delivery_heading(): void
    {
        $this->assertStringContainsString('Mail delivery', $this->render());
    }

    public function test_page_has_sign_in_providers_heading(): void
    {
        $this->assertStringContainsString('Sign-in providers', $this->render());
    }

    public function test_page_has_operations_heading(): void
    {
        $this->assertStringContainsString('Operations', $this->render());
    }

    // --- Routing block ---

    public function test_has_mail_driver_select(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('<select name="mail_driver"', $html);
    }

    public function test_has_mail_backup_select(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('<select name="mail_backup"', $html);
    }

    public function test_mail_driver_has_required_options(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('value="php"', $html);
        $this->assertStringContainsString('value="resend"', $html);
        $this->assertStringContainsString('value="mailjet"', $html);
        $this->assertStringContainsString('value="smtp"', $html);
    }

    public function test_mail_backup_has_none_option(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('value="none"', $html);
    }

    public function test_selected_driver_is_marked(): void
    {
        $html = $this->render(['values' => ['mail_driver' => 'resend', 'mail_backup' => 'none']]);
        // The "resend" option in mail_driver should be selected
        $this->assertMatchesRegularExpression('/<option[^>]*value="resend"[^>]*selected/i', $html);
    }

    // --- From fields ---

    public function test_has_from_email_field(): void
    {
        $this->assertStringContainsString('name="from_email"', $this->render());
    }

    public function test_has_from_name_field(): void
    {
        $this->assertStringContainsString('name="from_name"', $this->render());
    }

    // --- Provider credential fields ---

    public function test_has_resend_api_key_field(): void
    {
        $this->assertStringContainsString('name="resend_api_key"', $this->render());
    }

    public function test_has_mailjet_api_key_field(): void
    {
        $this->assertStringContainsString('name="mailjet_api_key"', $this->render());
    }

    public function test_has_mailjet_secret_key_field(): void
    {
        $this->assertStringContainsString('name="mailjet_secret_key"', $this->render());
    }

    public function test_has_telegram_bot_token_field(): void
    {
        $this->assertStringContainsString('name="telegram_bot_token"', $this->render());
    }

    public function test_has_telegram_chat_id_field(): void
    {
        $this->assertStringContainsString('name="telegram_chat_id"', $this->render());
    }

    public function test_has_smtp_fields(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('name="smtp_host"', $html);
        $this->assertStringContainsString('name="smtp_port"', $html);
        $this->assertStringContainsString('name="smtp_user"', $html);
        $this->assertStringContainsString('name="smtp_pass"', $html);
        $this->assertStringContainsString('name="smtp_encryption"', $html);
    }

    // --- Password input types for sensitive fields ---

    public function test_secret_fields_use_password_input_type(): void
    {
        $html = $this->render();
        // mailjet_secret_key contains "secret" => must be password type
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"[^>]*name="mailjet_secret_key"/i', $html);
        // smtp_pass contains "pass" => must be password type
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"[^>]*name="smtp_pass"/i', $html);
        // resend_api_key contains "api_key" => must be password type
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"[^>]*name="resend_api_key"/i', $html);
        // telegram_bot_token contains "token" => must be password type
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"[^>]*name="telegram_bot_token"/i', $html);
    }

    // --- Per-provider test forms ---

    public function test_has_test_form_for_resend(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('action="/admin/settings/test"', $html);
        $this->assertStringContainsString('name="provider" value="resend"', $html);
    }

    public function test_has_test_form_for_mailjet(): void
    {
        $this->assertStringContainsString('name="provider" value="mailjet"', $this->render());
    }

    public function test_has_test_form_for_smtp(): void
    {
        $this->assertStringContainsString('name="provider" value="smtp"', $this->render());
    }

    public function test_has_test_form_for_telegram(): void
    {
        $this->assertStringContainsString('name="provider" value="telegram"', $this->render());
    }

    public function test_test_forms_post_to_settings_test(): void
    {
        $html = $this->render();
        // All test forms must POST to /admin/settings/test
        $this->assertMatchesRegularExpression(
            '/<form[^>]*method="post"[^>]*action="\/admin\/settings\/test"/i',
            $html
        );
    }

    public function test_test_forms_include_csrf(): void
    {
        $html = $this->render(['csrf' => 'mycsrftoken']);
        // csrf token should appear in test forms
        $this->assertStringContainsString('value="mycsrftoken"', $html);
    }
}
