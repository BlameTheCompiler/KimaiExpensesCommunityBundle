<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\ExpenseType;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/expenses')]
final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'kimai_expenses_community', methods: ['GET'])]
    #[IsGranted('view_kimai_expenses_community')]
    public function index(): Response
    {
        $user = $this->getAuthenticatedUser();
        $canSeeOtherUsers = $this->isGranted('view_other_timesheet');

        return $this->render('@KimaiExpensesCommunity/expense/index.html.twig', [
            'expenses' => $this->expenses->findVisibleForUser($user, $canSeeOtherUsers),
            'title' => 'Expenses',
        ]);
    }

    #[Route('/create', name: 'kimai_expenses_community_create', methods: ['GET', 'POST'])]
    #[IsGranted('create_kimai_expenses_community')]
    public function create(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();

        $expense = new Expense();
        $expense->setUser($user);
        $expense->setDate(new \DateTime('now', new \DateTimeZone($user->getTimezone())));

        $form = $this->createForm(ExpenseType::class, $expense, [
            'action' => $this->generateUrl('kimai_expenses_community_create'),
            'can_edit_cost' => $this->isGranted('edit_kimai_expenses_community_cost'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Normal users cannot edit rates. Always take the rate from the
            // selected category on the server so the browser cannot bypass
            // the permission by modifying the HTML form.
            if (!$this->isGranted('edit_kimai_expenses_community_cost')) {
                $expense->setCost($expense->getCategory()->getDefaultCost());
            }

            $this->entityManager->persist($expense);
            $this->entityManager->flush();

            $this->addFlash('success', 'Expense created.');

            return $this->redirectToRoute('kimai_expenses_community');
        }

        return $this->render('@KimaiExpensesCommunity/expense/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'New expense',
        ]);
    }

    #[Route('/{id}/edit', name: 'kimai_expenses_community_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_kimai_expenses_community')]
    public function edit(int $id, Request $request): Response
    {
        $expense = $this->findExpense($id);
        $this->assertUserCanModify($expense);

        // Remember the persisted rate. A normal user is not allowed to submit
        // a changed cost, even if they manipulate the HTML form in a browser.
        $originalCost = $expense->getCost();

        $form = $this->createForm(ExpenseType::class, $expense, [
            'action' => $this->generateUrl('kimai_expenses_community_edit', ['id' => $id]),
            'can_edit_cost' => $this->isGranted('edit_kimai_expenses_community_cost'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('edit_kimai_expenses_community_cost')) {
                $expense->setCost($originalCost);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Expense updated.');

            return $this->redirectToRoute('kimai_expenses_community');
        }

        return $this->render('@KimaiExpensesCommunity/expense/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit expense',
        ]);
    }

    #[Route('/{id}/delete', name: 'kimai_expenses_community_delete', methods: ['POST'])]
    #[IsGranted('delete_kimai_expenses_community')]
    public function delete(int $id, Request $request): Response
    {
        $expense = $this->findExpense($id);
        $this->assertUserCanModify($expense);

        if (!$this->isCsrfTokenValid('delete-expense-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->entityManager->remove($expense);
        $this->entityManager->flush();

        $this->addFlash('success', 'Expense deleted.');

        return $this->redirectToRoute('kimai_expenses_community');
    }

    private function findExpense(int $id): Expense
    {
        $expense = $this->expenses->find($id);
        if (!$expense instanceof Expense) {
            throw $this->createNotFoundException('Expense not found.');
        }

        return $expense;
    }

    private function assertUserCanModify(Expense $expense): void
    {
        if ($expense->isExported() && !$this->isGranted('edit_exported_kimai_expenses_community')) {
            throw $this->createAccessDeniedException('Exported expenses cannot be changed.');
        }

        if (!$this->isGranted('view_other_timesheet')) {
            $currentUser = $this->getAuthenticatedUser();
            if ($expense->getUser()->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException();
            }
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('A logged-in Kimai user is required.');
        }

        return $user;
    }
}
