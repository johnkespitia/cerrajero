<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper;
use PHPUnit\Framework\TestCase;

class DianResponseMapperTest extends TestCase
{
    private DianResponseMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new DianResponseMapper();
    }

    public function test_sync_accepted_maps_to_dian_accepted(): void
    {
        $outcome = $this->mapper->map([
            'result' => [
                'IsValid' => 'true',
                'StatusCode' => '00',
                'StatusDescription' => 'Procesado Correctamente.',
                'XmlBytes' => base64_encode('<ApplicationResponse/>'),
            ],
        ], 'SendBillSync');

        $this->assertSame(DocumentStatus::DIAN_ACCEPTED, $outcome['target_status']);
        $this->assertTrue($outcome['is_valid']);
        $this->assertSame('00', $outcome['status_code']);
        $this->assertNotNull($outcome['application_response']);
        $this->assertSame([], $outcome['structured_errors']);
    }

    public function test_sync_invalid_with_recoverable_code_maps_to_recoverable(): void
    {
        $outcome = $this->mapper->map([
            'result' => [
                'IsValid' => 'false',
                'StatusCode' => '89',
                'ErrorMessage' => ['Codigo de tipo de documento ya usado.'],
            ],
        ], 'SendBillSync');

        $this->assertSame(DocumentStatus::DIAN_REJECTED_RECOVERABLE, $outcome['target_status']);
        $this->assertFalse($outcome['is_valid']);
        $this->assertCount(1, $outcome['structured_errors']);
    }

    public function test_sync_invalid_with_terminal_code_maps_to_terminal(): void
    {
        $outcome = $this->mapper->map([
            'result' => [
                'IsValid' => 'false',
                'StatusCode' => '99',
                'ErrorMessage' => [
                    ['code' => 'FAD06', 'message' => 'Firma invalida.'],
                ],
            ],
        ], 'SendBillSync');

        $this->assertSame(DocumentStatus::DIAN_REJECTED_TERMINAL, $outcome['target_status']);
        $this->assertSame('FAD06', $outcome['structured_errors'][0]['code']);
    }

    public function test_sync_unknown_shape_falls_back_to_validating(): void
    {
        $outcome = $this->mapper->map([
            'result' => ['SomethingElse' => 'x'],
        ], 'SendBillSync');

        $this->assertSame(DocumentStatus::DIAN_VALIDATING, $outcome['target_status']);
        $this->assertNull($outcome['is_valid']);
    }

    public function test_async_with_track_id_maps_to_track_received(): void
    {
        $outcome = $this->mapper->map([
            'result' => [
                'TrackId' => '8675309abc',
                'IsValid' => 'true',
            ],
        ], 'SendBillAsync');

        $this->assertSame(DocumentStatus::DIAN_TRACK_RECEIVED, $outcome['target_status']);
        $this->assertSame('8675309abc', $outcome['track_id']);
    }

    public function test_async_without_track_id_maps_to_recoverable(): void
    {
        $outcome = $this->mapper->map([
            'result' => ['IsValid' => 'false'],
        ], 'SendBillAsync');

        $this->assertSame(DocumentStatus::DIAN_REJECTED_RECOVERABLE, $outcome['target_status']);
    }

    public function test_recoverable_when_error_message_contains_timeout(): void
    {
        $outcome = $this->mapper->map([
            'result' => [
                'IsValid' => 'false',
                'StatusCode' => '500',
                'ErrorMessage' => ['Servicio en timeout, reintente.'],
            ],
        ], 'SendBillSync');

        $this->assertSame(DocumentStatus::DIAN_REJECTED_RECOVERABLE, $outcome['target_status']);
    }
}
