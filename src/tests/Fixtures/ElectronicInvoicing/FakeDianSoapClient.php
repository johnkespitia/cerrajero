<?php

namespace Tests\Fixtures\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use RuntimeException;

/**
 * In-memory `DianSoapClientInterface` used by the dispatcher / reconciler
 * tests. Recorded calls plus scripted responses keep tests hermetic.
 */
final class FakeDianSoapClient implements DianSoapClientInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    /** @var array<string, array<int, mixed>> */
    private array $scripted = [];

    public function script(string $operation, array $response): void
    {
        $this->scripted[$operation][] = $response;
    }

    public function fail(string $operation, \Throwable $exception): void
    {
        $this->scripted[$operation][] = $exception;
    }

    public function sendBillSync(string $fileName, string $zipBase64): array
    {
        return $this->pop('sendBillSync', compact('fileName', 'zipBase64'));
    }

    public function sendBillAsync(string $fileName, string $zipBase64): array
    {
        return $this->pop('sendBillAsync', compact('fileName', 'zipBase64'));
    }

    public function sendTestSetAsync(string $fileName, string $zipBase64, string $testSetId): array
    {
        return $this->pop('sendTestSetAsync', compact('fileName', 'zipBase64', 'testSetId'));
    }

    public function getStatus(string $trackId): array
    {
        return $this->pop('getStatus', ['trackId' => $trackId]);
    }

    public function getStatusZip(string $trackId): array
    {
        return $this->pop('getStatusZip', ['trackId' => $trackId]);
    }

    public function getNumberingRange(array $params): array
    {
        return $this->pop('getNumberingRange', $params);
    }

    public function sendEventUpdateStatus(string $fileName, string $zipBase64): array
    {
        return $this->pop('sendEventUpdateStatus', compact('fileName', 'zipBase64'));
    }

    public function getXmlByDocumentKey(string $cufe): array
    {
        return $this->pop('getXmlByDocumentKey', ['cufe' => $cufe]);
    }

    private function pop(string $operation, array $args): array
    {
        $this->calls[] = ['operation' => $operation, 'args' => $args];

        if (empty($this->scripted[$operation])) {
            throw new RuntimeException(sprintf(
                'FakeDianSoapClient received an unscripted "%s" call.',
                $operation
            ));
        }
        $next = array_shift($this->scripted[$operation]);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return (array) $next;
    }
}
