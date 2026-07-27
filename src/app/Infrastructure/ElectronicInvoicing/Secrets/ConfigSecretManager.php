<?php

namespace App\Infrastructure\ElectronicInvoicing\Secrets;

use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Default SecretManager implementation backed by Laravel config + env.
 *
 * Resolution order for a reference like "ref:hab/pin":
 *  1. Strip the optional "ref:" prefix.
 *  2. Look up `config('electronic-invoicing.secrets.hab/pin')`.
 *  3. Fall back to env(strtoupper(replace('/' with '_'))) e.g. HAB_PIN.
 *
 * If nothing matches, SecretUnavailableException is raised. The message only
 * exposes the reference name; the literal secret is NEVER thrown.
 */
final class ConfigSecretManager implements SecretManagerInterface
{
    /** @var ConfigRepository|null */
    private $config;

    public function __construct(?ConfigRepository $config = null)
    {
        $this->config = $config;
    }

    public function get(string $ref): string
    {
        $key = $this->normalise($ref);

        $fromConfig = $this->configValue('electronic-invoicing.secrets.' . $key);
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        $envKey = strtoupper(str_replace(['/', '-', '.'], '_', $key));
        $fromEnv = $this->envValue($envKey);
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        throw SecretUnavailableException::for($ref);
    }

    private function normalise(string $ref): string
    {
        if (strpos($ref, 'ref:') === 0) {
            return substr($ref, 4);
        }
        return $ref;
    }

    private function configValue(string $path)
    {
        if ($this->config !== null) {
            return $this->config->get($path);
        }
        if (function_exists('config')) {
            return config($path);
        }
        return null;
    }

    private function envValue(string $name)
    {
        if (function_exists('env')) {
            return env($name);
        }
        return getenv($name) !== false ? getenv($name) : null;
    }
}
