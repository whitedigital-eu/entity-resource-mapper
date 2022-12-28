<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\Attribute;

use Attribute;
use InvalidArgumentException;
use WhiteDigital\EntityResourceMapper\Security\Interface\AccessResolverInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class AccessResolverConfiguration
{
    public function __construct(private readonly string $className, private readonly ?array $config = null)
    {
        if (!is_a($this->className, AccessResolverInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('The access resolver class "%s" does not implement "%s".',
                $this->className, AccessResolverInterface::class));
        }
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }
}