<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Soap\Actions\AbstractDianAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetNumberingRangeAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetStatusAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetStatusZipAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetXmlByDocumentKeyAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendBillAsyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendBillSyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendEventUpdateStatusAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendTestSetAsyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\InvalidSoapPayloadException;
use App\Infrastructure\ElectronicInvoicing\Soap\SoapNamespaces;
use DOMDocument;
use PHPUnit\Framework\TestCase;

class DianSoapActionsTest extends TestCase
{
    public function test_each_action_exposes_expected_metadata(): void
    {
        $cases = [
            [new SendBillSyncAction(), 'SendBillSync'],
            [new SendBillAsyncAction(), 'SendBillAsync'],
            [new SendTestSetAsyncAction(), 'SendTestSetAsync'],
            [new GetStatusAction(), 'GetStatus'],
            [new GetStatusZipAction(), 'GetStatusZip'],
            [new GetNumberingRangeAction(), 'GetNumberingRange'],
            [new SendEventUpdateStatusAction(), 'SendEventUpdateStatus'],
            [new GetXmlByDocumentKeyAction(), 'GetXmlByDocumentKey'],
        ];

        foreach ($cases as [$action, $expectedName]) {
            $this->assertSame($expectedName, $action->operationName());
            $this->assertSame(
                AbstractDianAction::ACTION_NAMESPACE . '/' . $expectedName,
                $action->soapAction()
            );
        }
    }

    public function test_send_bill_sync_body_contains_fileName_and_contentFile(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $b64 = base64_encode('<Invoice/>');
        $body = (new SendBillSyncAction())->buildOperationElement($doc, [
            'fileName' => 'SETP1.zip',
            'contentFile' => $b64,
        ]);
        $doc->appendChild($body);

        $this->assertSame(SoapNamespaces::WCF_DIAN, $body->namespaceURI);
        $this->assertSame('SendBillSync', $body->localName);
        $this->assertSame('SETP1.zip', $this->valueOf($body, 'fileName'));
        $this->assertSame($b64, $this->valueOf($body, 'contentFile'));
    }

    public function test_content_file_is_passed_through_without_modification(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $b64 = base64_encode(str_repeat('UBL XML PAYLOAD ', 64));
        $body = (new SendBillAsyncAction())->buildOperationElement($doc, [
            'fileName' => 'batch.zip',
            'contentFile' => $b64,
        ]);
        $doc->appendChild($body);

        $this->assertSame($b64, $this->valueOf($body, 'contentFile'));
    }

    public function test_send_test_set_async_requires_testSetId(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $this->expectException(InvalidSoapPayloadException::class);
        $this->expectExceptionMessage('testSetId');
        (new SendTestSetAsyncAction())->buildOperationElement($doc, [
            'fileName' => 'set.zip',
            'contentFile' => base64_encode('<x/>'),
        ]);
    }

    public function test_invalid_base64_content_file_is_rejected(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $this->expectException(InvalidSoapPayloadException::class);
        $this->expectExceptionMessage('contentFile');
        (new SendBillSyncAction())->buildOperationElement($doc, [
            'fileName' => 'SETP1.zip',
            'contentFile' => '@@@ not base64 @@@',
        ]);
    }

    public function test_get_status_requires_track_id(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $this->expectException(InvalidSoapPayloadException::class);
        $this->expectExceptionMessage('trackId');
        (new GetStatusAction())->buildOperationElement($doc, []);
    }

    public function test_get_status_zip_emits_track_id(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $body = (new GetStatusZipAction())->buildOperationElement($doc, ['trackId' => 'TRACK-123']);
        $doc->appendChild($body);

        $this->assertSame('TRACK-123', $this->valueOf($body, 'trackId'));
    }

    public function test_get_numbering_range_emits_three_parameters(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $body = (new GetNumberingRangeAction())->buildOperationElement($doc, [
            'accountCode' => '900000000',
            'accountCodeT' => '800000000',
            'softwareCode' => '11111111-2222-3333-4444-555555555555',
        ]);
        $doc->appendChild($body);

        $this->assertSame('900000000', $this->valueOf($body, 'accountCode'));
        $this->assertSame('800000000', $this->valueOf($body, 'accountCodeT'));
        $this->assertSame(
            '11111111-2222-3333-4444-555555555555',
            $this->valueOf($body, 'softwareCode')
        );
    }

    public function test_get_xml_by_document_key_rejects_non_hex_keys(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $this->expectException(InvalidSoapPayloadException::class);
        $this->expectExceptionMessage('trackId');
        (new GetXmlByDocumentKeyAction())->buildOperationElement($doc, [
            'trackId' => 'too-short',
        ]);
    }

    public function test_get_xml_by_document_key_accepts_valid_cufe(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $cufe = str_repeat('a', 96);
        $body = (new GetXmlByDocumentKeyAction())->buildOperationElement($doc, ['trackId' => $cufe]);
        $doc->appendChild($body);

        $this->assertSame($cufe, $this->valueOf($body, 'trackId'));
    }

    public function test_send_event_update_status_emits_filename_and_zip(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $b64 = base64_encode('<ApplicationResponse/>');
        $body = (new SendEventUpdateStatusAction())->buildOperationElement($doc, [
            'fileName' => 'event-030.zip',
            'contentFile' => $b64,
        ]);
        $doc->appendChild($body);

        $this->assertSame('event-030.zip', $this->valueOf($body, 'fileName'));
        $this->assertSame($b64, $this->valueOf($body, 'contentFile'));
    }

    private function valueOf(\DOMElement $body, string $localName): string
    {
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child->textContent;
            }
        }
        $this->fail(sprintf('Operation body does not contain wcf:%s', $localName));
    }
}
