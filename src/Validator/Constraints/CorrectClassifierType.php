<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Validator\Constraints;

use Attribute;
use BackedEnum;
use Symfony\Component\Validator\Constraint;

use function sprintf;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CorrectClassifierType extends Constraint
{
    public function __construct(public BackedEnum $type)
    {
        parent::__construct();
    }

    public function getMessage(string $actual): string
    {
        return sprintf('Property must be classifier of type "%s", "%s" given.', $this->type->value, $actual);
    }
}
