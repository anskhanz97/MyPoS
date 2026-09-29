<?php

namespace App\Domain\Fiscal;

use App\Models\FiscalConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class FbrDiV112Transport implements FiscalTransport
{
    private const ENDPOINTS = ['sandbox' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb', 'production' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata'];

    public function submit(FiscalConfiguration $configuration, array $payload): FiscalTransportResult
    {
        abort_unless($configuration->authority === 'fbr_di' && $configuration->adapter_version === 'fbr-di-v1.12', 500, 'Unsupported fiscal adapter.');
        $token = $configuration->credentialData()['bearer_token'] ?? null;
        abort_unless(is_string($token) && $token !== '', 500, 'Fiscal credential is unavailable.');
        try {
            $response = Http::withToken($token)->acceptJson()->timeout(15)->connectTimeout(5)->post(self::ENDPOINTS[$configuration->environment], $payload);
        } catch (ConnectionException) {
            return new FiscalTransportResult('unknown', null, null, null, null, 'transport_timeout', 'Authority outcome is unknown. Reconcile before retrying.', null);
        }
        $hash = hash('sha256', $response->body());
        $status = $response->status();
        if ($status === 401) {
            return new FiscalTransportResult('rejected', null, null, null, $status, 'credential_error', 'Authority rejected the configured credential.', $hash);
        }
        if ($status >= 500) {
            return new FiscalTransportResult('unknown', null, null, null, $status, 'authority_unavailable', 'Authority outcome is unknown. Reconcile before retrying.', $hash);
        }
        $json = $response->json();
        if (! is_array($json)) {
            return new FiscalTransportResult('unknown', null, null, null, $status, 'malformed_response', 'Authority returned an unrecognized response.', $hash);
        }
        $reference = $json['invoiceNumber'] ?? null;
        $validation = data_get($json, 'validationResponse.status');
        if ($response->successful() && is_string($reference) && $reference !== '' && in_array(mb_strtolower((string) $validation), ['valid', 'accepted'], true)) {
            return new FiscalTransportResult('accepted', $reference, is_string($json['dated'] ?? null) ? $json['dated'] : null, null, $status, null, 'Accepted by FBR DI.', $hash);
        }
        $code = (string) (data_get($json, 'validationResponse.errorCode') ?? data_get($json, 'validationResponse.invoiceStatuses.0.errorCode') ?? 'business_rejection');

        return new FiscalTransportResult('rejected', null, null, null, $status, $code, 'Authority rejected the fiscal document. Review mapped business data.', $hash);
    }
}
