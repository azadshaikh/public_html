<?php

declare(strict_types=1);

namespace Modules\Billing\Contracts;

/**
 * Invoiceable Contract
 *
 * Interface for models that can be converted into invoice line items.
 * Implemented by entities that can appear on invoices.
 */
interface Invoiceable
{
    /**
     * Convert the model to an invoice line item array.
     *
     * @return array{
     *     description: string,
     *     quantity: int|float,
     *     unit_price: float,
     *     total: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     metadata: array<string, mixed>
     * }
     */
    public function toInvoiceLineItem(): array;

    /**
     * Get the total invoiceable amount including taxes.
     */
    public function getInvoiceableTotal(): float;

    /**
     * Get the description for the invoice line.
     */
    public function getInvoiceableDescription(): string;

    /**
     * Get the quantity for the invoice line.
     */
    public function getInvoiceableQuantity(): int|float;

    /**
     * Get the unit price for the invoice line.
     */
    public function getInvoiceableUnitPrice(): float;

    /**
     * Get the tax amount for the invoice line.
     */
    public function getInvoiceableTaxAmount(): float;

    /**
     * Get the primary key value of the model.
     */
    public function getKey(): mixed;
}
