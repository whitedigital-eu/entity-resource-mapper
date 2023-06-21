<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CorrectClassifierTypeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        /** @var CorrectClassifierType $constraint */
        if ($value->type !== $constraint->type) {
            $this->context->buildViolation($constraint->getMessage($value->type->value))->addViolation();
        }
    }
}
