<?php echo "<?php declare(strict_types = 1);\n"; ?>

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
use <?php echo $processor->getFullName() . ";\n"; ?>
use <?php echo $provider->getFullName() . ";\n"; ?>
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Serializer\Annotation\Groups;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[
    ApiResource(
        shortName: '<?php echo $entity_name; ?>',
        operations: [
            new Delete(
                requirements: ['id' => '\d+', ],
            ),
            new Get(
                requirements: ['id' => '\d+', ],
                normalizationContext: ['groups' => [self::ITEM, ], ],
            ),
            new GetCollection(
                normalizationContext: ['groups' => [self::READ, ], ],
            ),
            new Patch(
                requirements: ['id' => '\d+', ],
                denormalizationContext: ['groups' => [self::PATCH, ], ],
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
    #[Groups([self::READ, self::ITEM, ])]
    public mixed $id = null;

    #[Groups([self::READ, self::ITEM, ])]
    public ?DateTimeImmutable $createdAt = null;

    #[Groups([self::READ, self::ITEM, ])]
    public ?DateTimeImmutable $updatedAt = null;
}
