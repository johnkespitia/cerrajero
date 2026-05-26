<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Ports\UblBuilderInterface;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21CreditNoteBuilder;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21DebitNoteBuilder;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21DeePosBuilder;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21FevBuilder;
use App\Services\ElectronicInvoicing\Exceptions\BuilderNotRegisteredException;

/**
 * Maps DocumentType -> UblBuilderInterface implementation.
 *
 * The registry is the single seam through which DocumentEmitter resolves the
 * right XML builder for a given document type. Tests can pass a custom
 * registry to substitute any builder with a stub.
 */
final class UblBuilderRegistry
{
    /** @var array<string, UblBuilderInterface> */
    private $builders;

    /**
     * @param array<string, UblBuilderInterface> $builders Indexed by DocumentType::*.
     */
    public function __construct(array $builders)
    {
        $this->builders = [];
        foreach ($builders as $type => $builder) {
            $this->register($type, $builder);
        }
    }

    public static function default(): self
    {
        return new self([
            DocumentType::FEV => new Ubl21FevBuilder(),
            DocumentType::DEE_POS => new Ubl21DeePosBuilder(),
            DocumentType::NC => new Ubl21CreditNoteBuilder(),
            DocumentType::ND => new Ubl21DebitNoteBuilder(),
        ]);
    }

    public function register(string $documentType, UblBuilderInterface $builder): void
    {
        DocumentType::assert($documentType);
        $this->builders[$documentType] = $builder;
    }

    public function resolve(string $documentType): UblBuilderInterface
    {
        DocumentType::assert($documentType);
        if (!isset($this->builders[$documentType])) {
            throw BuilderNotRegisteredException::for($documentType);
        }
        return $this->builders[$documentType];
    }

    /**
     * @return array<int, string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->builders);
    }
}
