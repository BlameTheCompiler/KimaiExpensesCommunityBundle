<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A NumberType that hides trailing zeros when it renders its value.
 *
 * The database keeps 4 decimal places, but "1.0000" is noisy in a form.
 * This only changes what is displayed: users can still type decimals, and
 * nothing is rounded on the way in or out.
 *
 *   min_decimals = 0  ->  1.0000 shows as "1",    2.5000 shows as "2.5"
 *   min_decimals = 2  ->  1.0000 shows as "1.00", 0.7000 shows as "0.70",
 *                         0.6725 still shows as "0.6725"
 */
final class TrimmedDecimalType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $view->vars['value'] ?? null;

        // Only touch clean numeric strings (html5 mode always renders "1234.5678").
        // Anything else, such as a value the user typed that failed validation,
        // is left exactly as submitted.
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            $view->vars['value'] = self::trim($value, (int) $options['min_decimals']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'scale' => 4,
            'html5' => true,
            'min_decimals' => 0,
        ]);

        $resolver->setAllowedTypes('min_decimals', 'int');
    }

    public function getParent(): string
    {
        return NumberType::class;
    }

    private static function trim(string $value, int $minDecimals): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        $fraction = rtrim($fraction, '0');
        $fraction = str_pad($fraction, $minDecimals, '0');

        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }
}
