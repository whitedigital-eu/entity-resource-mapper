# Entity Resource Mapper Bundle

Extends Symfony / Api Platform functionality by helping to map Doctrine entity objects with Api Platform resource objects and offers other helpers such as filters, JSON Functions, etc.

## Requirements

PHP 8.1+

Symfony 6.1+

## Configuration

### ClassMapper service ###
You should create ClassMapper service configuration file, for example:

```php
namespace App\Service;

use App\Dto\CustumerDto;
use App\Entity\Customer;

use WhiteDigital\EntityResourceMapperBundle\Mapper\ClassMapper;

class ClassMapperConfigurator
{
    public function __invoke(ClassMapper $classMapper)
    {
        $classMapper->registerMapping(CustomerResource::class, Customer::class);
    }
}

```
and register it as configurator for ClassMapper service in your services.yaml file:
```yaml
    WhiteDigital\EntityResourceMapper\Mapper\ClassMapper:
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
                    EntityResourceMapperBundle:
                        is_bundle: true
                        type: attribute
                        prefix: 'WhiteDigital\EntityResourceMapper\Entity'
                        alias: EntityResourceMapper
```
### Filters ###
Following filters are currently available (filters works as described in Api Platform docs, except for comments below): 
- ResourceBooleanFilter
- ResourceDateFilter _(throws exception, if value is not a valid DateTime object)_
- ResourceEnumFilter _(same as SearchFilter but with explicit documentation)_
- ResourceExistsFilter
- ResourceJsonFilter _(new filter, TODO - register JSON functions...)_
- ResourceNumericFilter
- ResourceOrderFilter _(allows ordering by json values)_
- ResourceOrderCustomFilter _(Order filter which will order by custom SELECT fields, which are not included in root alias nor joins)_
- ResourceRangeFilter
- ResourceSearchFilter

### JSON Functions ### 
Following PostgreSQL functions are available in Doctrine and used in ResourceJsonFilter and ResourceOrderFilter:
- JSONB_PATH_EXISTS - PostgreSQL function jsonb_path_exists(%s::jsonb, %s)
- JSON_GET_TEXT - alias for %s->>%s
- JSON_ARRAY_LENGTH - PostgreSQL function json_array_length(%s)

## Tests

Run tests by:
```bash
$ vendor/bin/phpunit
```

## TODO ##
- Doctrine autoconfiguration
- JSON functions registration
- performance improvements
- explicit joins on dataprovider
- computed properties as querybuilder methods
