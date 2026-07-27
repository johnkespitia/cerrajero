<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use Tests\TestCase;

class ElectronicInvoicingServiceProviderTest extends TestCase
{
    public function test_metrics_recorder_is_resolved_as_in_memory_singleton_by_default(): void
    {
        $first = $this->app->make(MetricsRecorderInterface::class);
        $second = $this->app->make(MetricsRecorderInterface::class);

        $this->assertInstanceOf(InMemoryMetricsRecorder::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_logger_is_resolved_with_electronic_invoicing_channel_when_present(): void
    {
        $channels = (array) config('logging.channels', []);
        $this->assertArrayHasKey(
            'electronic-invoicing',
            $channels,
            'config/logging.php must declare the electronic-invoicing channel.'
        );

        $logger = $this->app->make(ElectronicInvoicingLoggerInterface::class);

        $this->assertInstanceOf(ElectronicInvoicingLogger::class, $logger);
    }

    public function test_logger_falls_back_when_electronic_invoicing_channel_missing(): void
    {
        config(['logging.channels.electronic-invoicing' => null]);
        $channels = array_filter((array) config('logging.channels', []));
        config(['logging.channels' => $channels]);

        $this->app->forgetInstance(ElectronicInvoicingLoggerInterface::class);

        $logger = $this->app->make(ElectronicInvoicingLoggerInterface::class);
        $this->assertInstanceOf(ElectronicInvoicingLogger::class, $logger);
    }
}
