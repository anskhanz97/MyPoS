<?php

namespace App\Domain\Printing;

use App\Domain\Offline\CanonicalPayload;

final class PrintAuthorizationSigner
{
    public function __construct(private readonly CanonicalPayload $canonical) {}

    /** @param array<string, mixed> $payload
     * @return array{algorithm:string,public_key:string,payload:array<string,mixed>,signature:string}
     */
    public function sign(array $payload): array
    {
        $seed = hash('sha256', (string) config('app.key').'|mypos-print-helper-v1', true);
        $pair = sodium_crypto_sign_seed_keypair($seed);
        $signature = sodium_crypto_sign_detached($this->canonical->json($payload), sodium_crypto_sign_secretkey($pair));

        return ['algorithm' => 'Ed25519', 'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)), 'payload' => $payload, 'signature' => base64_encode($signature)];
    }
}
