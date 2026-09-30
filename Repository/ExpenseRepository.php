<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;

final class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * @return Expense[]
     */
    public function findVisibleForUser(User $user, bool $canSeeOtherUsers): array
    {
        $qb = $this->createQueryBuilder('expense')
            ->leftJoin('expense.category', 'category')->addSelect('category')
            ->leftJoin('expense.customer', 'customer')->addSelect('customer')
            ->leftJoin('expense.project', 'project')->addSelect('project')
            ->leftJoin('expense.activity', 'activity')->addSelect('activity')
            ->leftJoin('expense.user', 'owner')->addSelect('owner')
            ->orderBy('expense.date', 'DESC')
            ->addOrderBy('expense.id', 'DESC');

        if (!$canSeeOtherUsers) {
            $qb->andWhere('expense.user = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByCategory(\KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory $category): int
    {
        return (int) $this->createQueryBuilder('expense')
            ->select('COUNT(expense.id)')
            ->andWhere('expense.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }
    /**
     * Create the base query used when retrieving expenses for invoices.
     *
     * Keeping this in the repository gives us one place to evolve the query
     * when we add receipt fields, custom fields, or more invoice filters later.
     */
    public function createInvoiceQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('expense')
            ->leftJoin('expense.category', 'category')
            ->addSelect('category')
            ->leftJoin('expense.user', 'expenseUser')
            ->addSelect('expenseUser')
            ->leftJoin('expense.customer', 'customer')
            ->addSelect('customer')
            ->leftJoin('expense.project', 'project')
            ->addSelect('project')
            ->leftJoin('expense.activity', 'activity')
            ->addSelect('activity');
    }
}
