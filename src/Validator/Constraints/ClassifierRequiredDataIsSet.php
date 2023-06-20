<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Validator\Constraints;

use Attribute;
use BackedEnum;
use Symfony\Component\Validator\Constraint;

use function sprintf;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ClassifierRequiredDataIsSet extends Constraint
{
    public function __construct(public BackedEnum $type, public array $required)
    {
        parent::__construct();
    }

    public function getMessage(): string
    {
        return sprintf('%s is required in \'data\' for \'%s\' type', implode(', ', $this->required), $this->type->value);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
