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
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canEditCost = (bool) $options['can_edit_cost'];
        $userZone = new \DateTimeZone((string) $options['timezone']);
        $utc = new \DateTimeZone('UTC');
        // Doctrine reads DATETIME columns as wall-clock time in PHP's default
        // timezone, so use that for both sides. No conversion happens, which is
        // why the stored time can't drift or roll over to another day.
        $builder
            ->add('date', DateTimePickerType::class, [
                'label' => 'Date and time',
                'model_timezone' => 'UTC',
                'view_timezone' => 'UTC',
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
        // The picker does no conversion. This shows the stored UTC instant as
        // wall-clock time in the user's timezone, and converts back on submit.
        $builder->get('date')->addModelTransformer(new CallbackTransformer(
            static function (?\DateTimeInterface $stored) use ($userZone, $utc): ?\DateTime {
                if ($stored === null) {
                    return null;
                }
                $local = \DateTimeImmutable::createFromInterface($stored)->setTimezone($userZone);

                return new \DateTime($local->format('Y-m-d H:i:s'), $utc);
            },
            static function (mixed $submitted) use ($userZone, $utc): \DateTime {
                if (!$submitted instanceof \DateTimeInterface) {
                    throw new TransformationFailedException('A date and time is required.');
                }

                return (new \DateTime($submitted->format('Y-m-d H:i:s'), $userZone))->setTimezone($utc);
            },
        ));

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'can_edit_cost' => false,
            'timezone' => 'UTC',
        ]);

        $resolver->setAllowedTypes('can_edit_cost', 'bool');
        $resolver->setAllowedTypes('timezone', 'string');
    }
}
