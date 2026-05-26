<?php

namespace App\Providers;

use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Services\ElectronicInvoicing\Certificate\CertificateSecretStoreInterface;
use App\Services\ElectronicInvoicing\Certificate\CertificateStorageInterface;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateSecretStore;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateStorage;
use App\Services\ElectronicInvoicing\LegacyPt\InMemoryLegacyPtArtifactStorage;
use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtArtifactStorageInterface;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\UnsignedXmlStorageInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service provider for Electronic Invoicing DIAN.
 *
 * Centralises the dependency container wiring for the EI pipeline so the
 * concrete adapters can be swapped in any environment without touching
 * domain code.
 *
 * Bindings:
 * - `MetricsRecorderInterface` -> `InMemoryMetricsRecorder` (singleton).
 *   Production overrides this with a Prometheus exporter.
 * - `ElectronicInvoicingLoggerInterface` -> `ElectronicInvoicingLogger`
 *   bound to the `electronic-invoicing` log channel when defined, else
 *   falling back to the default channel, else a `NullLogger`.
 */
class ElectronicInvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsRecorderInterface::class, function (): MetricsRecorderInterface {
            return new InMemoryMetricsRecorder();
        });

        $this->app->bind(ElectronicInvoicingLoggerInterface::class, function (Container $app): ElectronicInvoicingLoggerInterface {
            $logger = $this->resolveBaseLogger($app);

            return new ElectronicInvoicingLogger($logger);
        });

        $this->app->singleton(LegacyPtArtifactStorageInterface::class, function (): LegacyPtArtifactStorageInterface {
            return new InMemoryLegacyPtArtifactStorage();
        });

        $this->app->singleton(UnsignedXmlStorageInterface::class, function (): UnsignedXmlStorageInterface {
            return new InMemoryUnsignedXmlStorage();
        });

        $this->app->singleton(CertificateStorageInterface::class, function (): CertificateStorageInterface {
            return new InMemoryCertificateStorage();
        });

        $this->app->singleton(CertificateSecretStoreInterface::class, function (): CertificateSecretStoreInterface {
            return new InMemoryCertificateSecretStore();
        });
    }

    private function resolveBaseLogger(Container $app): LoggerInterface
    {
        $channels = (array) config('logging.channels', []);
        if (array_key_exists('electronic-invoicing', $channels)) {
            try {
                /** @var LogManager $manager */
                $manager = $app->make('log');

                return $manager->channel('electronic-invoicing');
            } catch (\Throwable $e) {
                // Fall through to default channel.
            }
        }
        if ($app->bound(LoggerInterface::class)) {
            return $app->make(LoggerInterface::class);
        }

        return new NullLogger();
    }
}
