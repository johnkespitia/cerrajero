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

    public function test_sign_raises_unavailable_when_no_material_is_bound_to_alias(): void
    {
        $signer = $this->newSigner();

        $this->expectException(XadesEpesSigningUnavailableException::class);
        $this->expectExceptionMessageMatches('/key material/i');
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

    public function test_sign_with_material_produces_verifiable_envelope(): void
    {
        if (! extension_loaded('openssl') || ! extension_loaded('dom')) {
            $this->markTestSkipped('OpenSSL + DOM extensions are required for this test.');
        }

        $material = $this->generateTestMaterial();

        $signer = $this->newSigner();
        $signed = $signer->signWithMaterial(
            '<?xml version="1.0"?>' .
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">' .
            '<cbc xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">' .
            '<ID>SETP990000001</ID></cbc></Invoice>',
            $material
        );

        // ds:Signature is anchored under ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent
        $this->assertStringContainsString('ext:UBLExtensions', $signed);
        $this->assertStringContainsString('ext:UBLExtension', $signed);
        $this->assertStringContainsString('ds:Signature', $signed);
        $this->assertStringContainsString('xades:SignedProperties', $signed);
        $this->assertStringContainsString('xades:SignaturePolicyIdentifier', $signed);
        $this->assertStringContainsString('ds:X509Certificate', $signed);
        $this->assertStringContainsString('SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"', $signed);
        $this->assertStringContainsString('CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"', $signed);

        // The signature MUST verify against the embedded X.509 certificate.
        $this->assertTrue($signer->verifySignature($signed));
    }

    public function test_sign_with_material_includes_signature_policy_identifier_from_config(): void
    {
        if (! extension_loaded('openssl') || ! extension_loaded('dom')) {
            $this->markTestSkipped('OpenSSL + DOM extensions are required for this test.');
        }

        $material = $this->generateTestMaterial();
        $signer = $this->newSigner();
        $signed = $signer->signWithMaterial('<?xml version="1.0"?><Invoice/>', $material);

        $this->assertStringContainsString(
            '<xades:Identifier>https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf</xades:Identifier>',
            $signed
        );

        $document = new \DOMDocument();
        $document->loadXML($signed);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('xades', \App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner::NS_XADES);
        $xpath->registerNamespace('ds', \App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner::NS_DS);
        $policyDigestNodes = $xpath->query('//xades:SigPolicyHash/ds:DigestValue');
        $this->assertGreaterThan(0, $policyDigestNodes->length, 'SigPolicyHash/DigestValue must exist.');
        $this->assertSame('dMqkBgDfJ+CMb6tJM7gQUFA0R5o=', trim((string) $policyDigestNodes->item(0)->nodeValue));
    }

    public function test_with_material_clones_the_signer_without_mutating_the_original(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension is required for this test.');
        }

        $material = $this->generateTestMaterial();
        $base = $this->newSigner();
        $bound = $base->withMaterial('alias-1', $material);

        $this->expectException(XadesEpesSigningUnavailableException::class);
        $base->sign('<?xml version="1.0"?><Invoice/>', 'alias-1');

        $signed = $bound->sign('<?xml version="1.0"?><Invoice/>', 'alias-1');
        $this->assertNotSame('', $signed);
    }

    public function test_verify_signature_returns_false_when_payload_tampered(): void
    {
        if (! extension_loaded('openssl') || ! extension_loaded('dom')) {
            $this->markTestSkipped('OpenSSL + DOM extensions are required for this test.');
        }
        $material = $this->generateTestMaterial();
        $signer = $this->newSigner();
        $signed = $signer->signWithMaterial(
            '<?xml version="1.0"?><Invoice><ID>1</ID></Invoice>',
            $material
        );

        $tampered = str_replace('<ID>1</ID>', '<ID>2</ID>', $signed);
        $this->assertFalse($signer->verifySignature($tampered));
    }

    private function generateTestMaterial(): array
    {
        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['CN' => 'Signer Test', 'O' => 'Campo Verde'], $keyPair, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $keyPair, 365, ['digest_alg' => 'sha256']);

        $certPem = '';
        openssl_x509_export($cert, $certPem);

        $privatePem = '';
        openssl_pkey_export($keyPair, $privatePem);

        return ['certificate' => $certPem, 'private_key' => $privatePem, 'chain_pem' => null];
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
