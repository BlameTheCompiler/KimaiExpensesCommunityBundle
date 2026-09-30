<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Invoice;

use App\Entity\Activity;
use App\Entity\MetaTableTypeInterface;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\ExportableItem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;

/**
 * Adapts a plugin Expense entity to Kimai's invoice system.
 *
 * Keeping this as a separate adapter means Expense itself does not need to
 * contain invoice-specific behavior.
 */
final class ExpenseInvoiceItem implements ExportableItem
{
    public function __construct(
        private readonly Expense $expense,
    ) {
    }

    public function getExpense(): Expense
    {
        return $this->expense;
    }

    public function getId(): ?int
    {
        return $this->expense->getId();
    }

    public function isExported(): bool
    {
        return $this->expense->isExported();
    }

    public function isBillable(): bool
    {
        return $this->expense->isBillable();
    }

    public function getAmount(): float
    {
        return (float) $this->expense->getQuantity();
    }

    public function getActivity(): ?Activity
    {
        return $this->expense->getActivity();
    }

    public function getProject(): ?Project
    {
        return $this->expense->getProject();
    }

    /**
     * Expenses use a fixed unit price rather than an hourly rate.
     *
     * For mileage, for example:
     *
     *     47.5 miles × $0.70 = $33.25
     */
    public function getFixedRate(): ?float
    {
        return (float) $this->expense->getCost();
    }

    public function getHourlyRate(): ?float
    {
        return null;
    }

    public function getRate(): float
    {
        return $this->expense->getTotal();
    }

    /**
     * Expenses currently have no separate internal/cost rate.
     */
    public function getInternalRate(): ?float
    {
        return null;
    }

    public function getUser(): ?User
    {
        return $this->expense->getUser();
    }

    /**
     * An expense has a point in time rather than a duration.
     *
     * Kimai's invoice merger expects both begin and end to be available, so
     * both are represented by the expense date.
     */
    public function getBegin(): ?\DateTime
    {
        return \DateTime::createFromInterface($this->expense->getDate());
    }

    public function getEnd(): ?\DateTime
    {
        return \DateTime::createFromInterface($this->expense->getDate());
    }

    public function getDuration(): ?int
    {
        return 0;
    }

    public function getDescription(): ?string
    {
        return $this->expense->getDescription();
    }

    /**
     * This allows invoice templates to identify the line as an expense.
     *
     * Example:
     *
     *     {% if entry.type == 'expense' %}
     */
    public function getType(): string
    {
        return 'expense';
    }

    /**
     * Expose the configured expense category to invoice templates.
     *
     * Example:
     *
     *     {{ entry.category }}
     */
    public function getCategory(): string
    {
        return $this->expense->getCategory()->getName();
    }

    /**
     * Custom fields are not implemented in Phase 2 yet.
     *
     * Returning an empty collection keeps the adapter compatible with
     * Kimai's ExportableItem contract and leaves room for Phase 3.
     */
    public function getMetaField(string $name): ?MetaTableTypeInterface
    {
        return null;
    }

    /**
     * @return MetaTableTypeInterface[]
     */
    public function getVisibleMetaFields(): array
    {
        return [];
    }

    /**
     * @return Collection<int, MetaTableTypeInterface>
     */
    public function getMetaFields(): Collection
    {
        return new ArrayCollection();
    }

    /**
     * Phase 2 expenses do not support tags yet.
     *
     * @return string[]
     */
    public function getTagsAsArray(): array
    {
        return [];
    }
}