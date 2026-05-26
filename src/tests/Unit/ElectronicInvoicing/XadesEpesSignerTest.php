<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigningUnavailableException;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class XadesEpesSignerTest extends TestCase
{
    private function fullPolicyConfig(): array
    {
        return [
            'algorithm' => 'RSA-SHA256',
            'canonicalization' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
            'policy_oid' => '2.16.170.1.5.2.1',
            'policy_url' => 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf',
            'policy_hash_b64' => 'dMqkBgDfJ+CMb6tJM7gQUFA0R5o=',
        ];
    }

    private function makeProvider(): CertificateProviderInterface
    {
        return new class implements CertificateProviderInterface {
            public function active(int $companyId, string $environment): array
            {
                return [
                    'alias' => 'test-cert',
                    'subject_cn' => 'TEST',
                    'issuer_cn' => 'TEST CA',
                    'serial_number' => '000',
                    'not_before' => new DateTimeImmutable('-1 day'),
                    'not_after' => new DateTimeImmutable('+1 year'),
                    'fingerprint_sha256' => str_repeat('a', 64),
                ];
            }

            public function load(int $companyId, string $environment): array
            {
                throw new RuntimeException('load() must not be reached by tests in this slice.');
            }
        };
    }

    private function newSigner(array $config = null): XadesEpesSigner
    {
        return new XadesEpesSigner(
            $this->makeProvider(),
            $config ?? $this->fullPolicyConfig()
        );
    }

    public function test_sign_rejects_empty_xml(): void
    {
        $signer = $this->newSigner();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');
        $signer->sign('', 'active');
    }

    public function test_sign_rejects_malformed_xml(): void
    {
        $signer = $this->newSigner();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not well-formed');
        $signer->sign('<Invoice><Unclosed>', 'active');
    }

    public function test_sign_rejects_empty_alias(): void
    {
        $signer = $this->newSigner();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alias must not be empty');
        $signer->sign('<?xml version="1.0"?><Invoice/>', '   ');
    }

    public function test_sign_raises_unavailable_when_policy_is_missing(): void
    {
        $config = $this->fullPolicyConfig();
        unset($config['policy_oid']);
        $signer = $this->newSigner($config);

        $this->expectException(XadesEpesSigningUnavailableException::class);
        $this->expectExceptionMessage('signature policy');
        $signer->sign('<?xml version="1.0"?><Invoice/>', 'active');
    }

    public function test_sign_raises_unavailable_when_xades_full_envelope_is_not_wired(): void
    {
        $signer = $this->newSigner();

        $this->expectException(XadesEpesSigningUnavailableException::class);
        $this->expectExceptionMessageMatches('/not yet wired|xmlsec/i');
        $signer->sign('<?xml version="1.0"?><Invoice><cbc:ID>1</cbc:ID></Invoice>', 'active');
    }

    public function test_sign_failure_message_does_not_leak_certificate_or_key_material(): void
    {
        $signer = $this->newSigner();

        try {
            $signer->sign('<?xml version="1.0"?><Invoice/>', 'active');
            $this->fail('Expected XadesEpesSigningUnavailableException.');
        } catch (XadesEpesSigningUnavailableException $e) {
            $message = $e->getMessage();
            $forbidden = [
                'BEGIN CERTIFICATE',
                'BEGIN RSA PRIVATE KEY',
                'BEGIN PRIVATE KEY',
                'password',
                'PIN',
                'private_key',
            ];
            foreach ($forbidden as $token) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $token,
                    $message,
                    sprintf('XAdES error message must not contain "%s".', $token)
                );
            }
        }
    }

    public function test_digest_sha256_matches_known_vector(): void
    {
        $signer = $this->newSigner();
        $expected = base64_encode(hash('sha256', 'abc', true));
        $this->assertSame($expected, $signer->digestSha256('abc'));
    }

    public function test_sign_raw_rsa_sha256_rejects_empty_private_key(): void
    {
        $signer = $this->newSigner();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key material is required');
        $signer->signRawRsaSha256('payload', '');
    }

    public function test_sign_raw_rsa_sha256_rejects_garbage_key(): void
    {
        $signer = $this->newSigner();
        $this->expectException(InvalidArgumentException::class);
        $signer->signRawRsaSha256('payload', 'not-a-pem');
    }

    public function test_sign_raw_rsa_sha256_signs_payload_and_signature_verifies(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension is required for this test.');
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($res, 'Could not generate test RSA key.');

        $privatePem = '';
        $this->assertTrue(openssl_pkey_export($res, $privatePem));
        $details = openssl_pkey_get_details($res);
        $publicPem = $details['key'];

        $signer = $this->newSigner();
        $payload = 'canonical-signed-info-placeholder';
        $signatureB64 = $signer->signRawRsaSha256($payload, $privatePem);

        $this->assertNotSame('', $signatureB64);
        $rawSig = base64_decode($signatureB64, true);
        $this->assertNotFalse($rawSig);

        $verify = openssl_verify($payload, $rawSig, $publicPem, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verify, 'Signature should verify against the matching public key.');
    }

    public function test_signature_algorithm_and_canonicalization_are_exposed(): void
    {
        $signer = $this->newSigner();
        $this->assertSame('RSA-SHA256', $signer->signatureAlgorithm());
        $this->assertSame(
            'http://www.w3.org/2001/10/xml-exc-c14n#',
            $signer->canonicalizationMethod()
        );
    }

    public function test_signature_algorithm_falls_back_to_default_when_missing_in_config(): void
    {
        $signer = $this->newSigner([
            'policy_oid' => 'x',
            'policy_url' => 'x',
            'policy_hash_b64' => 'x',
        ]);
        $this->assertSame('RSA-SHA256', $signer->signatureAlgorithm());
        $this->assertSame(
            'http://www.w3.org/2001/10/xml-exc-c14n#',
            $signer->canonicalizationMethod()
        );
    }
}
