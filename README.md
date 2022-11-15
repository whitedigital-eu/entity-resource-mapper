# Entity Resource Mapper Bundle

1) Extends Symfony / Api Platform functionality by helping to map Doctrine entity objects with Api Platform resource objects and offers other helpers such as filters, JSON Functions, etc.

2) Implements AuthorizationService which centralizes all authorization configuration and provides methods for authorizing resources in:
- Data provider - collection get
- Data provider - item get
- Data persister - item post/put/patch
- Data persister - item delete
- Individual resource in EntityToResourceMapper

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

use WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;

class ClassMapperConfigurator implements ClassMapperConfiguratorInterface
{
    public function __invoke(ClassMapper $classMapper)
    {
        $classMapper->registerMapping(CustomerResource::class, Customer::class);
    }
}

```
and register it as configurator for ClassMapper service in your services.yaml file:
```yaml
    WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface:
      class: App\Service\ClassMapperConfigurator
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
- JSONB_PATH_EXISTS(%s, %s) - PostgreSQL function jsonb_path_exists(%s::jsonb, %s)
- JSON_GET_TEXT(%s, %s) - PostgreSQL alias for %s->>%s
- JSON_ARRAY_LENGTH(%s) - PostgreSQL function json_array_length(%s)
- JSON_CONTAINS(%s, %s) - PostgreSQL alias for %s::jsonb @> '%s'

### Security ### 
Available operation types:
- `AuthorizationService::ALL` Includes all of the below
- `AuthorizationService::COL_GET` Collection GET 
- `AuthorizationService::ITEM_GET` Item GET
- `AuthorizationService::COL_POST` Collection POST
- `AuthorizationService::ITEM_PATCH` Item PUT + PATCH
- `AuthorizationService::ITEM_DELETE` Item DELETE

Available grant types:
- `GrantType::ALL` resource fully available  
- `GrantType::GROUP` only resources owned by same group available.
- `GrantType::OWN` only owned resource available (MUST also have valid group!)
- `GrantType::NONE` resource not available

AuthorizationService Configurator must be implemented. 

```php
// src/Service/Configurator/AuthorizationServiceConfigurator.php

use WhiteDigital\EntityResourceMapper\Security\AuthorizationServiceConfiguratorInterface;

final class AuthorizationServiceConfigurator implements AuthorizationServiceConfiguratorInterface
{
    public function __invoke(AuthorizationService $service): void
    {
        $service->setResources([
            ActivityResource::class => [
                AuthorizationService::ALL => ['ROLE_SUPER_ADMIN' => GrantType::ALL, 'ROLE_KAM' => GrantType::ALL],
                AuthorizationService::COL_GET => [, 'ROLE_JUNIOR_KAM' => GrantType::OWN],
                AuthorizationService::ITEM_GET => [, 'ROLE_JUNIOR_KAM' => GrantType::GROUP],
                AuthorizationService::COL_POST => [],
                AuthorizationService::ITEM_PATCH => [],
                AuthorizationService::ITEM_DELETE => [],
            ]]);
    
        //either mainResource or roles key must be set
        $service->setMenuStructure(
                [
                    ['name' => 'ACTIVITIES',
                        'mainResource' => ActivityResource::class,
                    ],
                    ['name' => 'REPORTS',
                        'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_KAM'],
                    ],
                 ]);
    }
}
```
register it as service:
```
WhiteDigital\EntityResourceMapper\Security\AuthorizationServiceConfiguratorInterface:
    class: AuthorizationServiceConfigurator
```
Use following methods:  
- In DataProvider, getCollection:
```php
$this->authorizationService->limitGetCollection($resourceClass, $queryBuilder); // This will affect queryBuilder object
```
- In DataProvider, getItem:
```php
$this->authorizationService->authorizeSingleObject($entity, AuthorizationService::ITEM_GET); // This will throw AccessDeniedException if not authorized
```
- In DataPersister, persist:
```php
$this->authorizationService->authorizeSingleObject($data, AuthorizationService::ITEM_PATCH); // This will throw AccessDeniedException if not authorized
// or
$this->authorizationService->authorizeSingleObject($data, AuthorizationService::COL_POST; // This will throw AccessDeniedException if not authorized
```
- In DataPersister, remove:
```php
$this->authorizationService->authorizeSingleObject($data, AuthorizationService::ITEM_DELETE); // This will throw AccessDeniedException if not authorized
```
- In any Resource, if you want to limit output for non-OWN or non-GROUP properties, add attribute to the resource class:
```php
AuthorizeResource(ownerProperty: 'owner', groupProperty: 'department', visibleProperties: ['owner']),
```
- In any Resource, if you want to make it public (for any authenticated user), set publicProperty to bool column in table: 
```php
AuthorizeResource(ownerProperty: 'owner', groupProperty: 'department', visibleProperties: ['owner'], publicProperty: 'isPublic'),
```
```
Same class must also set following property with correct normalization group:
```php
    #[Groups('deal_read')]
    #[ApiProperty(attributes: ["openapi_context" => ["description" => "If Authorization GrantType::OWN or GROUP is calculated, resource can be restricted."]])]
    public bool $isRestricted = false;
```

### Menu Builder ### 

This package ships with a menu builder functionality, that allows to define the overall menu structure and allows for
dynamic menu building based on current user limitations (authorization, rules)

To use menu builder service, you must first create a configurator class for this service that implements 
WhiteDigital\EntityResourceMapper\Interface\MenuBuilderServiceConfiguratorInterface

```php

use WhiteDigital\EntityResourceMapper\MenuBuilder\Interface\MenuBuilderServiceConfiguratorInterface;
use WhiteDigital\EntityResourceMapper\MenuBuilder\Services\MenuBuilderService;

final class MenuBuilderServiceConfigurator implements MenuBuilderServiceConfiguratorInterface
{
    public function __invoke(MenuBuilderService $service): void
    {
        //either mainResource or roles key must be set
        $service->setMenuStructure(
                [
                    [
                        'name' => 'ACTIVITIES',
                        'mainResource' => ActivityResource::class,
                    ],
                    [
                        'name' => 'REPORTS',
                        'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_KAM'],
                    ],
                 ]);
    }
}
```
Register the configurator class as a service:
```
WhiteDigital\EntityResourceMapper\MenuBuilder\Interface\MenuBuilderServiceConfiguratorInterface:
    class: MenuBuilderServiceConfigurator
```
And finally you can use the menubuilder and retrieve the filtered menu by calling the MenuBuilderService like so:

```php

use WhiteDigital\EntityResourceMapper\MenuBuilder\Services\MenuBuilderService;

class SomeClass
{
    public function someFunction(MenuBuilderService $service): void
    {
        $data = $service->getMenuForCurrentUser();
    }
}
```

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
