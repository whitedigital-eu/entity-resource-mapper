<?php

namespace WhiteDigital\EntityDtoMapper\Dto;

class BaseDto
{

    /**
     * @throws \Exception
     */
    final public function __construct(...$args)
    {
        $reflection = new \ReflectionClass($this);
        foreach ($args as $arg_name => $arg_value) {
            if (!property_exists(static::class, $arg_name)) {
                throw new \RuntimeException(sprintf('Property %s does not exist in class %s', $arg_name, static::class));
            }
            $property = $reflection->getProperty($arg_name);
            $type = $property->getType();
            if (is_string($arg_value) && $type->getName() === \DateTimeInterface::class) {
                $this->{$arg_name} = new \DateTimeImmutable($arg_value);
            } else
                if (!is_null($arg_value)) {
                    $this->{$arg_name} = $arg_value;
                }
        }
    }

    /**
     * @param array $normalized
     * @param array $context
     * @return static
     * @throws \Exception
     */
    public static function createFromNormalizedEntity(array $normalized): static
    {
        return new static(... $normalized);
    }

    public static function createEmptyDto(): static
    {
        return new static();
    }
}
