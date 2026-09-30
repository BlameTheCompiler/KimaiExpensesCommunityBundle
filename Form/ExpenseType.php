<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use KimaiPlugin\KimaiExpensesCommunityBundle\Form\Type\TrimmedDecimalType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
//use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canEditCost = (bool) $options['can_edit_cost'];
        $timezone = (string) $options['timezone'];

        // NOTE: Symfony submits fields in the order they are added here, not in
        // the order they appear on screen. Expense::setCategory() copies the
        // category's default cost onto the expense, so "category" must stay
        // BEFORE "cost" or that copy would overwrite a cost the user just typed.
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
                // The customer id on each option lets the form narrow this list
                // to the selected customer and auto-select a lone project.
                'choice_attr' => static function (Project $project): array {
                    return ['data-customer' => (string) ($project->getCustomer()?->getId() ?? '')];
                },
            ])
            ->add('activity', EntityType::class, [
                'class' => Activity::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
            ])
            ->add('category', EntityType::class, [
                'class' => ExpenseCategory::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                // Add the default rate to each option so the small bit of
                // client-side code can update the displayed rate immediately.
                'choice_attr' => static function (ExpenseCategory $category): array {
                    return ['data-default-cost' => $category->getDefaultCost()];
                },
                'query_builder' => static function ($repository) {
                    return $repository->createQueryBuilder('category')
                        ->andWhere('category.visible = :visible')
                        ->setParameter('visible', true)
                        ->orderBy('category.name', 'ASC');
                },
            ])
            ->add('quantity', TrimmedDecimalType::class, [
                'label' => 'Quantity',
            ])
            ->add('date', DateTimePickerType::class, [
                'label' => 'Date and time',
//                'widget' => 'single_text',
                'model_timezone' => 'UTC',
                'view_timezone' => $timezone,
            ])
            ->add('billable', CheckboxType::class, [
                'required' => false,
                'label' => 'Billable',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('cost', TrimmedDecimalType::class, [
                'label' => 'Cost per unit',
                // Show at least 2 decimals (1.00), more only when the rate needs them.
                'min_decimals' => 2,
                // Disabled for normal users. The server also enforces the
                // permission; disabling this field is only a UI convenience.
                'disabled' => !$canEditCost,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'can_edit_cost' => false,
            'timezone' => date_default_timezone_get(),
        ]);

        $resolver->setAllowedTypes('can_edit_cost', 'bool');
        $resolver->setAllowedTypes('timezone', 'string');
    }
}
