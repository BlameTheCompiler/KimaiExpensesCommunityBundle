<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Invoice;

use App\Entity\ExportableItem;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Repository\Query\InvoiceQuery;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;

/**
 * Supplies community-plugin expenses to Kimai's invoice system.
 *
 * InvoiceItemRepositoryInterface is automatically discovered by Kimai
 * through its AutoconfigureTag attribute, so no manual service tag is needed.
 */
final class ExpenseInvoiceItemRepository implements InvoiceItemRepositoryInterface
{
    public function __construct(
        private readonly ExpenseRepository $expenseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Find billable expenses matching the current Kimai invoice query.
     *
     * @return ExpenseInvoiceItem[]
     */
    public function getInvoiceItemsForQuery(InvoiceQuery $query): array
    {
        $qb = $this->expenseRepository
            ->createInvoiceQueryBuilder()
            ->andWhere('expense.billable = :billable')
            ->setParameter('billable', true)
            /*
             * An invoice item must have a project because Kimai uses the
             * project's customer when generating invoices.
             */
            ->andWhere('expense.project IS NOT NULL')
            /*
             * Kimai's invoice query defaults to non-exported items.
             * Respect either explicit state.
             */
            ->orderBy('expense.date', $query->getOrder())
            ->addOrderBy('expense.id', $query->getOrder());

        /*
         * Date range
         */
        if ($query->getBegin() !== null) {
            $qb
                ->andWhere('expense.date >= :expenseBegin')
                ->setParameter('expenseBegin', $query->getBegin());
        }

        if ($query->getEnd() !== null) {
            $qb
                ->andWhere('expense.date <= :expenseEnd')
                ->setParameter('expenseEnd', $query->getEnd());
        }

        /*
         * Export state.
         *
         * Kimai invoice creation normally requests STATE_NOT_EXPORTED,
         * but honoring STATE_EXPORTED as well keeps the repository compatible
         * with other invoice/query usages.
         */
        if ($query->isNotExported()) {
            $qb
                ->andWhere('expense.exported = :exported')
                ->setParameter('exported', false);
        } elseif ($query->isExported()) {
            $qb
                ->andWhere('expense.exported = :exported')
                ->setParameter('exported', true);
        }

        /*
         * Customer filtering.
         *
         * The expense's customer normally follows its project, but filtering
         * through the stored customer also supports expenses entered before
         * the project/customer relationship was selected.
         */
        if ($query->hasCustomers()) {
            $customerIds = $query->getCustomerIds();

            if ($customerIds !== []) {
                $qb
                    ->andWhere('project.customer IN (:customerIds)')
                    ->setParameter('customerIds', $customerIds);
            }
        }

        /*
         * Project filtering.
         */
        if ($query->hasProjects()) {
            $projectIds = $query->getProjectIds();

            if ($projectIds !== []) {
                $qb
                    ->andWhere('project.id IN (:projectIds)')
                    ->setParameter('projectIds', $projectIds);
            }
        }

        /*
         * Activity filtering.
         */
        if ($query->hasActivities()) {
            $activities = $query->getActivities();
            $activityIds = [];

            foreach ($activities as $activity) {
                if ($activity->getId() !== null) {
                    $activityIds[] = $activity->getId();
                }
            }

            if ($activityIds !== []) {
                $qb
                    ->andWhere('activity.id IN (:activityIds)')
                    ->setParameter('activityIds', $activityIds);
            }
        }

        /*
         * User filtering.
         *
         * This matters when an invoice query has been narrowed to one or
         * more users.
         */
        if ($query->hasUsers()) {
            $userIds = [];

            foreach ($query->getUsers() as $user) {
                if ($user->getId() !== null) {
                    $userIds[] = $user->getId();
                }
            }

            if ($userIds !== []) {
                $qb
                    ->andWhere('expenseUser.id IN (:userIds)')
                    ->setParameter('userIds', $userIds);
            }
        }

        /** @var Expense[] $expenses */
        $expenses = $qb->getQuery()->getResult();

        foreach ($expenses as $expense) {
            /*
             * A project is mandatory for invoice output. The SQL condition
             * above guarantees this, but keeping the check here makes the
             * adapter invariant explicit as well.
             */
            if ($expense->getProject() === null) {
                continue;
            }

            /*
             * If the project belongs to a different customer than the
             * expense's stored customer, use the project's customer as the
             * authoritative invoice relationship.
             */
            if (
                $expense->getCustomer() !== null
                && $expense->getProject()->getCustomer()->getId() !== $expense->getCustomer()->getId()
            ) {
                continue;
            }
            $items[] = new ExpenseInvoiceItem($expense);
        }
        return $items;
    }

    /**
     * Mark all expenses that participated in an invoice as exported.
     *
     * Kimai passes every invoice entry to every registered invoice-item
     * repository, so only process our own adapter type.
     *
     * @param ExportableItem[] $invoiceItems
     */
    public function setExported(array $invoiceItems): void
    {
        $changed = false;

        foreach ($invoiceItems as $invoiceItem) {
            if (!$invoiceItem instanceof ExpenseInvoiceItem) {
                continue;
            }

            $expense = $invoiceItem->getExpense();

            if (!$expense->isExported()) {
                $expense->setExported(true);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }
}