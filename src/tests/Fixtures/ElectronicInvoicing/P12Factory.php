<?php

namespace Tests\Fixtures\ElectronicInvoicing;

/**
 * Generates throw-away `.p12` containers for tests so we never commit
 * a real certificate to the repo and never depend on external fixtures.
 *
 * Usage:
 *
 *   $factory = new P12Factory();
 *   $artifact = $factory->generate(['subject_cn' => 'Campo Verde']);
 *   $p12 = $artifact['p12'];           // raw bytes
 *   $password = $artifact['password']; // plaintext
 *   $fingerprint = $artifact['fingerprint_sha256'];
 *
 * The generated certificate is self-signed and valid for 365 days
 * from "now" by default.
 */
class P12Factory
{
    /**
     * @param  array{
     *     subject_cn?: string,
     *     issuer_cn?: string,
     *     password?: string,
     *     valid_days?: int,
     * }  $overrides
     *
     * @return array{p12: string, password: string, fingerprint_sha256: string, cert_pem: string}
     */
    public function generate(array $overrides = []): array
    {
        $subjectCn = $overrides['subject_cn'] ?? 'Test Cert';
        $issuerCn = $overrides['issuer_cn'] ?? $subjectCn;
        $password = $overrides['password'] ?? 'p12-pass-1234';
        $validDays = $overrides['valid_days'] ?? 365;

        $keyConfig = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $keyPair = openssl_pkey_new($keyConfig);
        if ($keyPair === false) {
            throw new \RuntimeException('openssl_pkey_new failed: ' . $this->lastError());
        }

        $dn = [
            'C' => 'CO',
            'ST' => 'Cundinamarca',
            'L' => 'Bogota',
            'O' => 'Campo Verde Tests',
            'OU' => 'Fiscal',
            'CN' => $subjectCn,
            'emailAddress' => 'fiscal@test.local',
        ];
        $csr = openssl_csr_new($dn, $keyPair, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            throw new \RuntimeException('openssl_csr_new failed: ' . $this->lastError());
        }

        $cert = openssl_csr_sign($csr, null, $keyPair, $validDays, ['digest_alg' => 'sha256']);
        if ($cert === false) {
            throw new \RuntimeException('openssl_csr_sign failed: ' . $this->lastError());
        }

        $certPem = '';
        openssl_x509_export($cert, $certPem);
        if ($certPem === '') {
            throw new \RuntimeException('openssl_x509_export failed: ' . $this->lastError());
        }

        if ($issuerCn !== $subjectCn) {
            // For tests that require a distinct issuer CN, swap the embedded
            // issuer with the requested value by re-signing through a fresh
            // CA. We keep this branch cheap: build a tiny CA on the fly.
            return $this->generateWithIssuer($subjectCn, $issuerCn, $password, $validDays);
        }

        $p12 = '';
        $ok = openssl_pkcs12_export($cert, $p12, $keyPair, $password);
        if (! $ok || $p12 === '') {
            throw new \RuntimeException('openssl_pkcs12_export failed: ' . $this->lastError());
        }

        return [
            'p12' => $p12,
            'password' => $password,
            'fingerprint_sha256' => $this->fingerprint($certPem),
            'cert_pem' => $certPem,
        ];
    }

    private function generateWithIssuer(string $subjectCn, string $issuerCn, string $password, int $validDays): array
    {
        $keyConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $caKey = openssl_pkey_new($keyConfig);
        $caCsr = openssl_csr_new(['CN' => $issuerCn], $caKey, ['digest_alg' => 'sha256']);
        $caCert = openssl_csr_sign($caCsr, null, $caKey, $validDays + 365, ['digest_alg' => 'sha256']);

        $endKey = openssl_pkey_new($keyConfig);
        $endCsr = openssl_csr_new(['CN' => $subjectCn], $endKey, ['digest_alg' => 'sha256']);
        $endCert = openssl_csr_sign($endCsr, $caCert, $caKey, $validDays, ['digest_alg' => 'sha256']);

        $certPem = '';
        openssl_x509_export($endCert, $certPem);

        $p12 = '';
        openssl_pkcs12_export($endCert, $p12, $endKey, $password);

        return [
            'p12' => $p12,
            'password' => $password,
            'fingerprint_sha256' => $this->fingerprint($certPem),
            'cert_pem' => $certPem,
        ];
    }

    private function fingerprint(string $certPem): string
    {
        $clean = preg_replace('/-----[A-Z ]+-----|\s+/', '', $certPem) ?? '';
        $der = base64_decode($clean, true);

        return hash('sha256', (string) $der);
    }

    private function lastError(): string
    {
        $messages = [];
        while ($message = openssl_error_string()) {
            $messages[] = $message;
        }

        return implode(' / ', $messages);
    }
}
