<?php echo "<?php declare(strict_types = 1);\n"; ?>

namespace <?php echo $namespace; ?>;

<?php
foreach ($uses as $use) {
    echo 'use ' . $use . ";\n";
}
?>
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface;

final class ClassMapperConfigurator implements ClassMapperConfiguratorInterface
{
    public function __invoke(ClassMapper $classMapper): void
    {
<?php
foreach ($mapping as $map) {
    echo '        $classMapper->registerMapping(' . $map['resource'] . ', ' . $map['entity'] . ($map['condition'] ? ', ' . $map['condition'] : '') . ($map['callback'] ? ', ' . $map['callback'] : '') . ');' . "\n";
}
?>
    }
}
