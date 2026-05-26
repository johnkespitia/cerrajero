<?php

namespace App\Services\ElectronicInvoicing\Certificate;

use DateTimeImmutable;

/**
 * Value object returned by `P12CertificateParser`.
 */
final class ParsedCertificate
{
    public function __construct(
        public readonly string $subjectCn,
        public readonly string $issuerCn,
        public readonly string $serialNumber,
        public readonly DateTimeImmutable $notBefore,
        public readonly DateTimeImmutable $notAfter,
        public readonly string $fingerprintSha256,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subject_cn' => $this->subjectCn,
            'issuer_cn' => $this->issuerCn,
            'serial_number' => $this->serialNumber,
            'not_before' => $this->notBefore->format(DATE_ATOM),
            'not_after' => $this->notAfter->format(DATE_ATOM),
            'fingerprint_sha256' => $this->fingerprintSha256,
        ];
    }
}
