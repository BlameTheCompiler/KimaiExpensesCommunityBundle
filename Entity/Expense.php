<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Entity;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'kimai2_kimai_expenses_community')]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $date;

    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ExpenseCategory $category;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Assert\NotBlank]
    private string $quantity = '1.0000';

    /**
     * The cost is copied from the selected category at creation time.
     * Storing it on the expense keeps historical rates stable when a category
     * is changed later.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Assert\GreaterThanOrEqual(0)]
    private string $cost = '1.0000';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $billable = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $exported = false;

    public function __construct()
    {
        $this->date = new \DateTime('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * The column holds UTC. Doctrine reads it back in PHP's default timezone,
     * so re-label the digits as UTC to get the correct instant.
     */
    public function getDate(): \DateTimeInterface
    {
        return new \DateTime($this->date->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = \DateTime::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'));
        return $this;
    }

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategory $category): self
    {
        $this->category = $category;
        $this->cost = $category->getDefaultCost();
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;
        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string|float|int $quantity): self
    {
        $this->quantity = number_format((float) $quantity, 4, '.', '');
        return $this;
    }

    public function getCost(): string
    {
        return $this->cost;
    }

    public function setCost(string|float|int $cost): self
    {
        $this->cost = number_format((float) $cost, 4, '.', '');
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isBillable(): bool
    {
        return $this->billable;
    }

    public function setBillable(bool $billable): self
    {
        $this->billable = $billable;
        return $this;
    }

    public function isExported(): bool
    {
        return $this->exported;
    }

    public function setExported(bool $exported): self
    {
        $this->exported = $exported;
        return $this;
    }

    /**
     * Kimai's documented expense model is quantity × cost.
     */
    public function getTotal(): float
    {
        return (float) $this->quantity * (float) $this->cost;
    }
}
