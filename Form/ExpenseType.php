<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
//use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
        // Doctrine reads DATETIME columns as wall-clock time in PHP's default
        // timezone, so use that for both sides. No conversion happens, which is
        // why the stored time can't drift or roll over to another day.
        $timezone = date_default_timezone_get();
        $builder
            ->add('date', DateTimePickerType::class, [
                'label' => 'Date and time',
                'model_timezone' => $timezone,
                'view_timezone' => $timezone,
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
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'scale' => 4,
                'html5' => true,
            ])
            ->add('cost', NumberType::class, [
                'label' => 'Cost per unit',
                'scale' => 4,
                'html5' => true,
                // Disabled for normal users. The server also enforces the
                // permission; disabling this field is only a UI convenience.
                'disabled' => !$canEditCost,
            ])
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
            ])
            ->add('activity', EntityType::class, [
                'class' => Activity::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— None —',
            ])
            ->add('billable', CheckboxType::class, [
                'required' => false,
                'label' => 'Billable',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'can_edit_cost' => false,
        ]);

        $resolver->setAllowedTypes('can_edit_cost', 'bool');
    }
}
