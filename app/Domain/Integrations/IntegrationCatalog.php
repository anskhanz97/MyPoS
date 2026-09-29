<?php

namespace App\Domain\Integrations;

use Illuminate\Validation\Rule;

final class IntegrationCatalog
{
    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return [
            'smtp' => $this->definition('Email (SMTP)', 'communication', 'Send receipts through the merchant mail server.', ['host', 'port', 'encryption', 'from_address', 'from_name'], ['username', 'password']),
            'meta_whatsapp' => $this->definition('WhatsApp Business', 'communication', 'Send approved receipt templates with Meta Cloud API.', ['business_account_id', 'phone_number_id', 'api_version', 'template_name', 'template_language', 'daily_limit'], ['access_token', 'app_secret', 'verify_token']),
            'twilio_sms' => $this->definition('SMS via Twilio', 'communication', 'Send transactional receipt links and delivery updates.', ['from_number', 'messaging_service_sid', 'daily_limit'], ['account_sid', 'auth_token']),
            'google_drive' => $this->definition('Google Drive', 'storage', 'Pick product images from a merchant Google Drive.', ['client_id', 'api_key', 'project_number'], []),
            'microsoft_onedrive' => $this->definition('Microsoft OneDrive', 'storage', 'Pick product images through Microsoft sign-in.', ['client_id', 'authority', 'redirect_uri'], ['client_secret']),
            'stripe' => $this->definition('Stripe', 'payment', 'Store card connector credentials for a future approved checkout flow.', ['publishable_key', 'account_id'], ['secret_key', 'webhook_secret']),
            'bank_transfer' => $this->definition('Bank transfer', 'payment', 'Show bank details for a manually verified transfer.', ['account_title', 'bank_name', 'iban', 'account_number', 'instructions'], []),
            'raast' => $this->definition('Raast', 'payment', 'Show a Raast ID or alias for a manually verified transfer.', ['account_title', 'raast_id', 'instructions'], []),
            'easypaisa' => $this->definition('Easypaisa', 'payment', 'Show a wallet destination for a manually verified transfer.', ['account_title', 'wallet_number', 'instructions'], []),
            'jazzcash' => $this->definition('JazzCash', 'payment', 'Show a wallet destination for a manually verified transfer.', ['account_title', 'wallet_number', 'instructions'], []),
        ];
    }

    /** @return array<string,mixed> */
    public function get(string $provider): array
    {
        $definition = $this->all()[$provider] ?? null;
        abort_unless(is_array($definition), 422, 'Choose a supported integration.');

        return $definition;
    }

    /** @return array<string,mixed> */
    public function rules(string $provider): array
    {
        $definition = $this->get($provider);
        $modes = $definition['category'] === 'payment' && $provider !== 'stripe' ? ['manual'] : ['sandbox', 'live'];
        $rules = [
            'mode' => ['required', Rule::in($modes)],
            'state' => ['required', Rule::in(['draft', 'disabled', 'active'])],
            'version' => ['nullable', 'integer', 'min:1'],
            'config' => ['present', 'array'],
            'credentials' => ['sometimes', 'array'],
            'clear_credentials' => ['sometimes', 'array'],
            'clear_credentials.*' => [Rule::in($definition['secret_fields'])],
        ];
        foreach ($definition['config_fields'] as $field) {
            $rules["config.{$field}"] = ['nullable', 'string', 'max:500'];
        }
        foreach ($definition['secret_fields'] as $field) {
            $rules["credentials.{$field}"] = ['nullable', 'string', 'max:4000'];
        }

        return $rules;
    }

    /**
     * @param  list<string>  $configFields
     * @param  list<string>  $secretFields
     * @return array{label:string,category:string,description:string,config_fields:list<string>,secret_fields:list<string>}
     */
    private function definition(string $label, string $category, string $description, array $configFields, array $secretFields): array
    {
        return ['label' => $label, 'category' => $category, 'description' => $description, 'config_fields' => $configFields, 'secret_fields' => $secretFields];
    }
}
