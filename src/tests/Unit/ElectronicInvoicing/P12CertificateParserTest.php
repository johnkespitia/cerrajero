<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Services\ElectronicInvoicing\Certificate\P12CertificateParser;
use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use PHPUnit\Framework\TestCase;

class P12CertificateParserTest extends TestCase
{
    public function test_parses_subject_issuer_serial_and_validity_window(): void
    {
        $factory = new P12Factory();
        $artifact = $factory->generate([
            'subject_cn' => 'Campo Verde S.A.S.',
            'issuer_cn' => 'Test CA',
        ]);

        $parser = new P12CertificateParser();
        $result = $parser->parse($artifact['p12'], $artifact['password']);

        $this->assertSame('Campo Verde S.A.S.', $result->subjectCn);
        $this->assertSame('Test CA', $result->issuerCn);
        $this->assertNotSame('', $result->serialNumber);
        $this->assertSame($artifact['fingerprint_sha256'], $result->fingerprintSha256);
        $this->assertGreaterThan($result->notBefore, $result->notAfter);
    }

    public function test_throws_when_password_is_wrong(): void
    {
        $factory = new P12Factory();
        $artifact = $factory->generate(['password' => 'real-password']);

        $parser = new P12CertificateParser();

        $this->expectException(InvalidCertificateException::class);
        $parser->parse($artifact['p12'], 'wrong-password');
    }

    public function test_throws_when_payload_is_empty(): void
    {
        $parser = new P12CertificateParser();

        try {
            $parser->parse('', 'any');
            $this->fail('Expected InvalidCertificateException not thrown');
        } catch (InvalidCertificateException $e) {
            $this->assertSame(InvalidCertificateException::CODE_EMPTY_PAYLOAD, $e->errorCode());
        }
    }

    public function test_fingerprint_is_deterministic_for_the_same_cert(): void
    {
        $factory = new P12Factory();
        $artifact = $factory->generate();

        $parser = new P12CertificateParser();
        $first = $parser->parse($artifact['p12'], $artifact['password']);
        $second = $parser->parse($artifact['p12'], $artifact['password']);

        $this->assertSame($first->fingerprintSha256, $second->fingerprintSha256);
    }
}
