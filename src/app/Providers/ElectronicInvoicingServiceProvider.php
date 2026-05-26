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
use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Infrastructure\ElectronicInvoicing\Certificates\P12CertificateProvider;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Services\ElectronicInvoicing\Storage\DianResponseStorageInterface;
use App\Services\ElectronicInvoicing\Storage\InMemoryDianResponseStorage;
use App\Services\ElectronicInvoicing\Storage\InMemorySignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\SignedXmlStorageInterface;
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

        $this->app->singleton(SignedXmlStorageInterface::class, function (): SignedXmlStorageInterface {
            return new InMemorySignedXmlStorage();
        });

        $this->app->bind(CertificateProviderInterface::class, function (Container $app): CertificateProviderInterface {
            return new P12CertificateProvider(
                $app->make(CertificateStorageInterface::class),
                $app->make(CertificateSecretStoreInterface::class),
            );
        });

        $this->app->bind(XadesEpesSigner::class, function (Container $app): XadesEpesSigner {
            $config = function_exists('config')
                ? (array) config('electronic-invoicing.signature', [])
                : [];

            return new XadesEpesSigner(
                $app->make(CertificateProviderInterface::class),
                $config
            );
        });

        $this->app->bind(\App\Services\ElectronicInvoicing\SigningCoordinator::class, function (Container $app): \App\Services\ElectronicInvoicing\SigningCoordinator {
            return new \App\Services\ElectronicInvoicing\SigningCoordinator(
                $app->make(UnsignedXmlStorageInterface::class),
                $app->make(SignedXmlStorageInterface::class),
                $app->make(CertificateProviderInterface::class),
                $app->make(XadesEpesSigner::class),
                $app->make(MetricsRecorderInterface::class),
                $app->make(ElectronicInvoicingLoggerInterface::class),
            );
        });

        $this->app->singleton(DianResponseStorageInterface::class, function (): DianResponseStorageInterface {
            return new InMemoryDianResponseStorage();
        });

        $this->app->singleton(\App\Services\ElectronicInvoicing\Contingency\CircuitBreakerInterface::class, function (): \App\Services\ElectronicInvoicing\Contingency\CircuitBreakerInterface {
            $config = function_exists('config') ? (array) config('electronic-invoicing.circuit_breaker', []) : [];

            return new \App\Services\ElectronicInvoicing\Contingency\InMemoryCircuitBreaker(
                failureThreshold: (int) ($config['failure_threshold'] ?? 5),
                recoverySeconds: (int) ($config['recovery_seconds'] ?? 60),
            );
        });

        $this->app->bind(\App\Services\ElectronicInvoicing\Contingency\ContingencyManager::class, function (Container $app): \App\Services\ElectronicInvoicing\Contingency\ContingencyManager {
            return new \App\Services\ElectronicInvoicing\Contingency\ContingencyManager(
                $app->make(\App\Services\ElectronicInvoicing\Contingency\CircuitBreakerInterface::class),
                $app->make(MetricsRecorderInterface::class),
                $app->make(ElectronicInvoicingLoggerInterface::class),
            );
        });

        $this->app->bind(\App\Services\ElectronicInvoicing\DianDispatcher::class, function (Container $app): \App\Services\ElectronicInvoicing\DianDispatcher {
            $mode = function_exists('config')
                ? (string) config('electronic-invoicing.dispatch.mode', 'sync')
                : 'sync';

            return new \App\Services\ElectronicInvoicing\DianDispatcher(
                $app->make(SignedXmlStorageInterface::class),
                $app->make(DianResponseStorageInterface::class),
                $app->make(\App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface::class),
                new \App\Services\ElectronicInvoicing\Dispatch\DianZipPackager(),
                new \App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper(),
                $app->make(MetricsRecorderInterface::class),
                $app->make(ElectronicInvoicingLoggerInterface::class),
                $mode,
                $app->make(\App\Services\ElectronicInvoicing\Contingency\ContingencyManager::class),
            );
        });

        $this->app->bind(\App\Services\ElectronicInvoicing\DocumentReconciler::class, function (Container $app): \App\Services\ElectronicInvoicing\DocumentReconciler {
            $config = function_exists('config') ? (array) config('electronic-invoicing.reconciler', []) : [];

            return new \App\Services\ElectronicInvoicing\DocumentReconciler(
                $app->make(\App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface::class),
                $app->make(DianResponseStorageInterface::class),
                new \App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper(),
                $app->make(\App\Services\ElectronicInvoicing\Contingency\ContingencyManager::class),
                $app->make(MetricsRecorderInterface::class),
                $app->make(ElectronicInvoicingLoggerInterface::class),
                (int) ($config['interval_minutes'] ?? 5),
                (int) ($config['stuck_after_minutes'] ?? 10),
            );
        });

        $this->app->bind(\App\Services\ElectronicInvoicing\Radian\RadianEventService::class, function (Container $app): \App\Services\ElectronicInvoicing\Radian\RadianEventService {
            return new \App\Services\ElectronicInvoicing\Radian\RadianEventService(
                $app->make(\App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface::class),
                $app->make(\App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner::class),
                $app->make(SignedXmlStorageInterface::class),
                $app->make(DianResponseStorageInterface::class),
                $app->make(\App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface::class),
                new \App\Services\ElectronicInvoicing\Radian\RadianEventBuilder(),
                new \App\Services\ElectronicInvoicing\Dispatch\DianZipPackager(),
                new \App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper(),
                $app->make(MetricsRecorderInterface::class),
                $app->make(ElectronicInvoicingLoggerInterface::class),
            );
        });

        $this->app->bind(\App\Services\ElectronicInvoicing\SyncContingencyDocumentsService::class, function (Container $app): \App\Services\ElectronicInvoicing\SyncContingencyDocumentsService {
            $config = function_exists('config') ? (array) config('electronic-invoicing.contingency', []) : [];

            return new \App\Services\ElectronicInvoicing\SyncContingencyDocumentsService(
                $app->make(\App\Services\ElectronicInvoicing\SigningCoordinator::class),
                $app->make(\App\Services\ElectronicInvoicing\DianDispatcher::class),
                $app->make(MetricsRecorderInterface::class),
                $app->make(ElectronicInvoicingLoggerInterface::class),
                (int) ($config['max_window_hours'] ?? 48),
            );
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
