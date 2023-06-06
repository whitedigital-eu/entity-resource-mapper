<?php echo "<?php declare(strict_types = 1);\n"; ?>

<?php use WhiteDigital\EntityResourceMapper\Maker\MakeApiResource; ?>

namespace <?php echo $namespace; ?>;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\GroupFilter;
<?php
foreach ($uses as $use) {
    echo 'use ' . $use . ";\n";
}
?>
use <?php echo $processor->getFullName() . ";\n"; ?>
use <?php echo $provider->getFullName() . ";\n"; ?>
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Serializer\Annotation\Groups;
<?php if ($hasBool = ([] !== ($filters[MakeApiResource::F_BOOL] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceBooleanFilter;
<?php } ?>
<?php if ($hasDate = ([] !== ($filters[MakeApiResource::F_DATE] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceDateFilter;
<?php } ?>
<?php if ($hasEnum = ([] !== ($filters[MakeApiResource::F_ENUM] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceEnumFilter;
<?php } ?>
<?php if ($hasJson = ([] !== ($filters[MakeApiResource::F_ARRAY] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceJsonFilter;
<?php } ?>
<?php if ($hasNumeric = ([] !== ($filters[MakeApiResource::F_NUMERIC] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceNumericFilter;
<?php } ?>
<?php if ($hasOrder = ([] !== ($order ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceOrderFilter;
<?php } ?>
<?php if ($hasRange = ([] !== ($filters[MakeApiResource::F_RANGE] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceRangeFilter;
<?php } ?>
<?php if ($hasSearch = ([] !== ($filters[MakeApiResource::F_SEARCH] ?? []))) { ?>
use WhiteDigital\EntityResourceMapper\Filters\ResourceSearchFilter;
<?php } ?>
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

#[
    ApiResource(
        shortName: '<?php echo $entity_name; ?>',
        operations: [
            new Delete(
                requirements: ['id' => '\d+', ],
            ),
            new Get(
                requirements: ['id' => '\d+', ],
                normalizationContext: ['groups' => [self::READ, ], ],
            ),
            new GetCollection(
                normalizationContext: ['groups' => [self::READ, ], ],
            ),
            new Patch(
                requirements: ['id' => '\d+', ],
                denormalizationContext: ['groups' => [self::WRITE, ], ],
            ),
            new Post(
                denormalizationContext: ['groups' => [self::WRITE, ], ],
            ),
        ],
        normalizationContext: ['groups' => [self::READ, ], ],
        denormalizationContext: ['groups' => [self::WRITE, ], ],
        order: ['createdAt' => Criteria::DESC, ],
        provider: <?php echo $provider->getShortName(); ?>::class,
        processor: <?php echo $processor->getShortName(); ?>::class,
    ),
    ApiFilter(GroupFilter::class, arguments: ['parameterName' => 'groups', 'overrideDefaultGroups' => false, ]),
    <?php if ($hasBool) { ?>
    ApiFilter(ResourceBooleanFilter::class, properties: <?php echo json_encode($filters[MakeApiResource::F_BOOL]); ?>),
    <?php } ?>
    <?php if ($hasDate) { ?>
    ApiFilter(ResourceDateFilter::class, properties: <?php echo json_encode($filters[MakeApiResource::F_DATE]); ?>),
    <?php } ?>
    <?php if ($hasJson) { ?>
    ApiFilter(ResourceJsonFilter::class, properties: <?php echo json_encode($filters[MakeApiResource::F_ARRAY]); ?>),
    <?php } ?>
    <?php if ($hasNumeric) { ?>
    ApiFilter(ResourceNumericFilter::class, properties: <?php echo json_encode($filters[MakeApiResource::F_NUMERIC]); ?>),
    <?php } ?>
    <?php if ($hasRange) { ?>
    ApiFilter(ResourceRangeFilter::class, properties: <?php echo json_encode($filters[MakeApiResource::F_RANGE]); ?>),
    <?php } ?>
    <?php if ($hasEnum) { ?>
    ApiFilter(ResourceEnumFilter::class, properties: [<?php
        foreach ($filters['enum'] as $enum) {
            echo "'" . $enum . "' => " . $enums[$enum] . ', ';
        }
    ?>]),
    <?php } ?>
    <?php if ($hasOrder) { ?>
    ApiFilter(ResourceOrderFilter::class, properties: <?php echo json_encode($order); ?>),
    <?php } ?>
    <?php if ($hasSearch) { ?>
    ApiFilter(ResourceSearchFilter::class, properties: <?php echo json_encode($filters[MakeApiResource::F_SEARCH]); ?>),
    <?php } ?>
]
class <?php echo $class_name; ?> extends BaseResource
{
    public const PREFIX = '<?php echo $prefix . $separator; ?>';

<?php
foreach ($groups as $group) {
    echo '    private const ' . strtoupper($group) . " = self::PREFIX . '$group'; // $prefix$separator$group\n";
}
?>

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ, ])]
    public mixed $id = null;

    #[Groups([self::READ, ])]
    public ?UTCDateTimeImmutable $createdAt = null;

    #[Groups([self::READ, ])]
    public ?UTCDateTimeImmutable $updatedAt = null;

<?php
foreach ($properties as $property => $options) {
    if (null !== $options['header']) {
        echo "    /** @var {$options['header']}[]|null */\n";
    }

    echo '    #[Groups([';
    foreach ($groups as $group) {
        echo 'self::' . strtoupper($group) . ', ';
    }
    echo "])]\n";

    $type = $options['type'];
    if ('mixed' !== $type) {
        $type = '?' . $type;
    }
    echo '    public ' . $type . ' $' . $property . ' = null;' . "\n\n";
}
?>
}
