<?php

namespace App\Domain\Integrations;

use App\Logging\OperationalLogger;
use App\Models\Merchant;
use App\Models\MerchantEmailSetting;
use App\Models\MerchantIntegration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class IntegrationManager
{
    private ?string $testOperationId = null;

    public function __construct(private readonly IntegrationCatalog $catalog) {}

    /** @param array<string,mixed> $data */
    public function save(Merchant $merchant, string $provider, array $data): MerchantIntegration
    {
        $definition = $this->catalog->get($provider);
        $integration = MerchantIntegration::query()->firstOrNew(['merchant_id' => $merchant->id, 'provider' => $provider]);
        if ($integration->exists && isset($data['version']) && $integration->version !== (int) $data['version']) {
            abort(409, 'Integration settings changed in another session. Refresh and try again.');
        }
        $config = Arr::only((array) ($data['config'] ?? []), $definition['config_fields']);
        $credentials = (array) ($integration->credentials ?? []);
        foreach (Arr::only((array) ($data['credentials'] ?? []), $definition['secret_fields']) as $key => $value) {
            if (is_string($value) && $value !== '') {
                $credentials[$key] = $value;
            }
        }
        foreach ((array) ($data['clear_credentials'] ?? []) as $key) {
            unset($credentials[$key]);
        }
        if (($data['state'] ?? 'draft') === 'active') {
            $missing = $this->missingRequired($provider, $config, $credentials);
            abort_if($missing !== [], 422, 'Complete these fields before activation: '.implode(', ', $missing).'.');
        }
        $integration->fill(['category' => $definition['category'], 'mode' => $data['mode'], 'state' => $data['state'], 'config' => $config, 'credentials' => $credentials ?: null, 'version' => $integration->exists ? $integration->version + 1 : 1])->save();
        app(OperationalLogger::class)->integration($provider, 'configuration.saved', 'Integration settings saved.', ['merchant_id' => $merchant->id, 'status' => 'succeeded', 'subject' => 'integration:'.$integration->public_id, 'context' => ['mode' => $integration->mode, 'state' => $integration->state]]);
        if ($provider === 'smtp') {
            MerchantEmailSetting::query()->updateOrCreate(['merchant_id' => $merchant->id], ['enabled' => $integration->state === 'active', 'provider_label' => 'merchant SMTP', 'from_address' => (string) ($config['from_address'] ?? ''), 'from_name' => (string) ($config['from_name'] ?? '')]);
        }

        return $integration->refresh();
    }

    /** @return array<string,mixed> */
    public function safe(MerchantIntegration $integration): array
    {
        $definition = $this->catalog->get($integration->provider);
        $credentials = (array) ($integration->credentials ?? []);

        return ['id' => $integration->public_id, 'provider' => $integration->provider, 'label' => $definition['label'], 'category' => $integration->category, 'description' => $definition['description'], 'mode' => $integration->mode, 'state' => $integration->state, 'config' => $integration->config ?? [], 'configured_credentials' => array_keys(array_filter($credentials, fn (string $value): bool => $value !== '')), 'version' => $integration->version, 'last_test' => ['state' => $integration->last_test_state, 'message' => $integration->last_test_message, 'at' => $integration->last_tested_at?->toAtomString()]];
    }

    /** @return array{state:string,message:string} */
    public function test(MerchantIntegration $integration): array
    {
        $this->testOperationId = 'integration-test:'.Str::uuid();
        app(OperationalLogger::class)->integration($integration->provider, 'connection_test.started', 'Integration connection check started.', ['merchant_id' => $integration->merchant_id, 'operation_id' => $this->testOperationId, 'subject' => 'integration:'.$integration->public_id, 'status' => 'started', 'context' => ['mode' => $integration->mode]]);
        $config = (array) $integration->config;
        $credentials = (array) $integration->credentials;
        $missing = $this->missingRequired($integration->provider, $config, $credentials);
        if ($missing !== []) {
            return $this->recordTest($integration, 'failed', 'Missing: '.implode(', ', $missing).'.');
        }
        if ($integration->mode !== 'live' || in_array($integration->provider, ['bank_transfer', 'raast', 'easypaisa', 'jazzcash', 'google_drive', 'microsoft_onedrive', 'smtp'], true)) {
            return $this->recordTest($integration, 'passed', $integration->mode === 'live' ? 'Configuration is complete. No message or payment was sent.' : 'Sandbox configuration is ready. No external request was made.');
        }
        try {
            $response = match ($integration->provider) {
                'twilio_sms' => Http::withBasicAuth($credentials['account_sid'], $credentials['auth_token'])->timeout(8)->get("https://api.twilio.com/2010-04-01/Accounts/{$credentials['account_sid']}.json"),
                'meta_whatsapp' => Http::withToken($credentials['access_token'])->timeout(8)->get("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}", ['fields' => 'display_phone_number,verified_name']),
                'stripe' => Http::withToken($credentials['secret_key'])->timeout(8)->get('https://api.stripe.com/v1/account'),
                default => null,
            };
        } catch (\Throwable $exception) {
            $this->recordTest($integration, 'failed', 'The provider could not be reached. Check connectivity and try again.');
            throw $exception;
        }

        return $this->recordTest($integration, $response?->successful() ? 'passed' : 'failed', $response?->successful() ? 'Provider accepted the credentials. No message or payment was sent.' : 'Provider rejected the connection. Check the saved credentials.');
    }

    /**
     * @param  array<string, string>  $config
     * @param  array<string, string>  $credentials
     * @return list<string>
     */
    public function missingRequired(string $provider, array $config, array $credentials): array
    {
        $required = match ($provider) {
            'smtp' => [['config', 'host'], ['config', 'port'], ['config', 'from_address'], ['config', 'from_name'], ['credentials', 'username'], ['credentials', 'password']],
            'meta_whatsapp' => [['config', 'phone_number_id'], ['config', 'api_version'], ['config', 'template_name'], ['config', 'template_language'], ['credentials', 'access_token'], ['credentials', 'app_secret'], ['credentials', 'verify_token']],
            'twilio_sms' => [['config', ($config['messaging_service_sid'] ?? '') !== '' ? 'messaging_service_sid' : 'from_number'], ['credentials', 'account_sid'], ['credentials', 'auth_token']],
            'google_drive' => [['config', 'client_id'], ['config', 'api_key'], ['config', 'project_number']],
            'microsoft_onedrive' => [['config', 'client_id'], ['config', 'authority'], ['config', 'redirect_uri']],
            'stripe' => [['config', 'publishable_key'], ['credentials', 'secret_key'], ['credentials', 'webhook_secret']],
            'bank_transfer' => [['config', 'account_title'], ['config', 'bank_name'], ['config', ($config['iban'] ?? '') !== '' ? 'iban' : 'account_number']],
            'raast' => [['config', 'account_title'], ['config', 'raast_id']],
            'easypaisa', 'jazzcash' => [['config', 'account_title'], ['config', 'wallet_number']],
            default => [],
        };

        return array_values(array_map(fn (array $item): string => $item[1], array_filter($required, function (array $item) use ($config, $credentials): bool {
            $values = $item[0] === 'config' ? $config : $credentials;

            return ! isset($values[$item[1]]) || trim((string) $values[$item[1]]) === '';
        })));
    }

    /** @return array{state:string,message:string} */
    private function recordTest(MerchantIntegration $integration, string $state, string $message): array
    {
        $integration->update(['last_test_state' => $state, 'last_test_message' => $message, 'last_tested_at' => now()]);
        app(OperationalLogger::class)->integration($integration->provider, 'connection_test.'.$state, $message, ['merchant_id' => $integration->merchant_id, 'operation_id' => $this->testOperationId, 'subject' => 'integration:'.$integration->public_id, 'status' => $state === 'passed' ? 'succeeded' : 'failed', 'level' => $state === 'passed' ? 'info' : 'warning', 'context' => ['mode' => $integration->mode]]);

        return compact('state', 'message');
    }
}
