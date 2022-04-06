# Entity Dto Mapper Bundle

Extends Symfony / Api Platform functionality by helping to map Doctrine entity with Api Platform resource classes and offer usefull functionality (Filters, etc)

## Configuration

### ClassMapper service ###
You should create ClassMapper service configuration file, for example:

```php
namespace App\Service;

use App\Dto\CustumerDto;
use App\Entity\Customer;

use WhiteDigital\EntityDtoMapperBundle\Mapper\ClassMapper;

class ClassMapperConfigurator
{
    public function __invoke(ClassMapper $classMapper)
    {
        $classMapper->registerMapping(CustomerDto::class, Customer::class);
    }
}

```
and register it as configurator for ClassMapper service in your services.yaml file:
```yaml
    WhiteDigital\EntityDtoMapper\Mapper\ClassMapper:
        configurator: '@App\Service\ClassMapperConfigurator'
```
### Doctrine ###

Doctrine configuration should be updated with mappings:

> **_TODO:_** Bundle should autoconfigure it
 
```yaml
                mappings:
                    App:
                        is_bundle: false
                        type: attribute
                        dir: '%kernel.project_dir%/src/Entity'
                        prefix: 'App\Entity'
                        alias: App
                    EntityDtoMapperBundle:
                        is_bundle: true
                        type: attribute
                        prefix: 'WhiteDigital\EntityDtoMapper\Entity'
                        alias: EntityDtoMapper
```
## Tests

Run tests by:
```bash
$ vendor/bin/phpunit
```

## TODO ##
- doctrine autoconfiguration
- how to call normalizer from static function from BaseEntity/Dto
- datetimenormalizer dependancy?
- Move Filters & other extensions
- What about pagination?
