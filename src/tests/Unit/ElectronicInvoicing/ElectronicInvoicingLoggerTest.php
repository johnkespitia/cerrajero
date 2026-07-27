<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ElectronicInvoicingLoggerTest extends TestCase
{
    public function test_info_emits_event_type_in_context(): void
    {
        $captured = [];
        $logger = new ElectronicInvoicingLogger($this->captureLogger($captured));

        $logger->info('document.emitted', ['extra' => 'foo']);

        $this->assertCount(1, $captured);
        $this->assertSame('info', $captured[0]['level']);
        $this->assertSame('document.emitted', $captured[0]['message']);
        $this->assertSame('document.emitted', $captured[0]['context']['event_type']);
        $this->assertSame('foo', $captured[0]['context']['extra']);
    }

    public function test_with_correlation_id_attaches_correlation_id_to_subsequent_records(): void
    {
        $captured = [];
        $logger = (new ElectronicInvoicingLogger($this->captureLogger($captured)))
            ->withCorrelationId('corr-123');

        $logger->info('document.signing.started');

        $this->assertSame('corr-123', $captured[0]['context']['correlation_id']);
    }

    public function test_with_electronic_document_attaches_id_and_does_not_mutate_original(): void
    {
        $captured = [];
        $base = new ElectronicInvoicingLogger($this->captureLogger($captured));
        $scoped = $base->withElectronicDocument(42);

        $scoped->warning('document.signing.unavailable');
        $base->warning('document.signing.unavailable');

        $this->assertCount(2, $captured);
        $this->assertSame(42, $captured[0]['context']['electronic_document_id']);
        $this->assertArrayNotHasKey('electronic_document_id', $captured[1]['context']);
    }

    public function test_pii_fields_are_masked_in_top_level_keys(): void
    {
        $captured = [];
        $logger = new ElectronicInvoicingLogger($this->captureLogger($captured));

        $logger->info('acquirer.captured', [
            'identification_number' => '1234567890',
            'email' => 'guest@example.com',
            'phone_number' => '+573001234567',
            'safe_field' => 'kept-as-is',
        ]);

        $context = $captured[0]['context'];
        $this->assertNotSame('1234567890', $context['identification_number']);
        $this->assertNotSame('guest@example.com', $context['email']);
        $this->assertNotSame('+573001234567', $context['phone_number']);
        $this->assertSame('kept-as-is', $context['safe_field']);
    }

    public function test_pii_fields_are_masked_recursively_inside_nested_arrays(): void
    {
        $captured = [];
        $logger = new ElectronicInvoicingLogger($this->captureLogger($captured));

        $logger->info('acquirer.persisted', [
            'acquirer' => [
                'first_name' => 'Camila',
                'document_number' => '900123456',
                'meta' => ['nit' => '900123456-1'],
            ],
        ]);

        $acquirer = $captured[0]['context']['acquirer'];
        $this->assertNotSame('Camila', $acquirer['first_name']);
        $this->assertNotSame('900123456', $acquirer['document_number']);
        $this->assertNotSame('900123456-1', $acquirer['meta']['nit']);
    }

    public function test_mask_value_preserves_length_signal_for_debuggability(): void
    {
        $masked = ElectronicInvoicingLogger::maskValue('900123456');

        $this->assertNotSame('900123456', $masked);
        $this->assertStringStartsWith('9', $masked);
        $this->assertStringEndsWith('6', $masked);
        $this->assertSame(strlen('900123456'), strlen($masked));
    }

    /**
     * @param  array<int, array{level: string, message: string, context: array<string,mixed>}>  $sink
     */
    private function captureLogger(array &$sink): LoggerInterface
    {
        return new class($sink) implements LoggerInterface {
            /** @var array<int, array{level: string, message: string, context: array<string,mixed>}> */
            private array $sink;

            public function __construct(array &$sink)
            {
                $this->sink = &$sink;
            }

            public function emergency($message, array $context = []): void { $this->capture('emergency', $message, $context); }
            public function alert($message, array $context = []): void { $this->capture('alert', $message, $context); }
            public function critical($message, array $context = []): void { $this->capture('critical', $message, $context); }
            public function error($message, array $context = []): void { $this->capture('error', $message, $context); }
            public function warning($message, array $context = []): void { $this->capture('warning', $message, $context); }
            public function notice($message, array $context = []): void { $this->capture('notice', $message, $context); }
            public function info($message, array $context = []): void { $this->capture('info', $message, $context); }
            public function debug($message, array $context = []): void { $this->capture('debug', $message, $context); }
            public function log($level, $message, array $context = []): void { $this->capture((string) $level, $message, $context); }

            private function capture(string $level, $message, array $context): void
            {
                $this->sink[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
