<?php declare(strict_types = 1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use function array_key_exists;

class ClassifierRequiredDataIsSetValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        /** @var DataSet $constraint */
        foreach ($constraint->required as $required) {
            if ($value->type === $constraint->type && ([] === $value->data || !array_key_exists($required, $value->data))) {
                $this->context->buildViolation($constraint->getMessage())->addViolation();
            }
        }
    }
}
