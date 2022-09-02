<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Attribute;

use Attribute;

/**
 * Add this attribute to those properties of child classes of BaseApiResource class, that contain self referencing
 * resources, by adding this attribute, only root elements will contain the referenced resources
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class SkipCircularReferenceCheck
{
}