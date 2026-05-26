<?php

namespace App\Infrastructure\ElectronicInvoicing\Secrets;

use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;

/**
 * In-memory SecretManager implementation. Used by tests to inject fake PINs
 * and certificate passwords without touching env or config disk.
 *
 * The "ref:" prefix is stripped transparently to mirror the production
 * resolver behaviour.
 */
final class ArraySecretManager implements SecretManagerInterface
{
    /** @var array<string, string> */
    private $secrets;

    /**
     * @param array<string, string> $secrets Keyed by reference (e.g. "hab/pin").
     */
    public function __construct(array $secrets = [])
    {
        $this->secrets = [];
        foreach ($secrets as $key => $value) {
            $this->set((string) $key, (string) $value);
        }
    }

    public function set(string $ref, string $value): void
    {
        $this->secrets[$this->normalise($ref)] = $value;
    }

    public function get(string $ref): string
    {
        $key = $this->normalise($ref);
        if (!array_key_exists($key, $this->secrets)) {
            throw SecretUnavailableException::for($ref);
        }
        return $this->secrets[$key];
    }

    private function normalise(string $ref): string
    {
        if (strpos($ref, 'ref:') === 0) {
            return substr($ref, 4);
        }
        return $ref;
    }
}
