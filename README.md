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
Symfony 6.3+

## Installation
The recommended way to install is via Composer:
```bash
composer require whitedigital-eu/entity-resource-mapper-bundle
```

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
        // with Callback - must return true for mapping to be active
        $classMapper->registerMapping(PublicHtmlResource::class, Html::class, callback: static fn (array $context) => !self::isAdmin($context));
        $classMapper->registerMapping(AdminHtmlResource::class, Html::class, callback: static fn (array $context) => self::isAdmin($context));
    }
    
    /**
     * IsAdmin or else IsPublic.
     */
    private static function isAdmin(array $context): bool
    {
        return array_key_exists('request_uri', $context) && str_starts_with($context['request_uri'], '/api/admin');
    }
}

```
and register it as configurator for ClassMapper service in your services.yaml file:
```yaml
    WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface:
      class: App\Service\ClassMapperConfigurator
```
Additionally, you can use Mapping attribute to register mapping:
```php
use App\Dto\CustumerDto;
use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;

#[ORM\Entity]
#[Mapping(CustumerDto::class)]
class Customer ...
```
```php
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
use App\Entity\Customer;

#[Mapping(Customer::class)]
class CustumerDto ...
```

### Filters ###
Following filters are currently available (filters works as described in Api Platform docs, except for comments below): 
- ResourceBooleanFilter
- ResourceDateFilter _(throws exception, if value is not a valid DateTime object)_
- ResourceEnumFilter _(same as SearchFilter but with explicit documentation)_
- ResourceExistsFilter
- ResourceJsonFilter _(new filter)_
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

### DBAL Types ###
This bundle comes with and autoconfigures following dbal types to use UTC time zone:
- date
- datetime
- date_immutable
- datetime_immutable

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
- `GrantType::LIMITED` resource is available with limitations
- `GrantType::NONE` resource not available

AuthorizationService Configurator must be implemented.

```php
// src/Service/Configurator/AuthorizationServiceConfigurator.php

use WhiteDigital\EntityResourceMapper\Resource\BaseResource;use WhiteDigital\EntityResourceMapper\Security\AuthorizationServiceConfiguratorInterface;

final class AuthorizationServiceConfigurator implements AuthorizationServiceConfiguratorInterface
{
    public function __invoke(AuthorizationService $service): void
    {
        $service->setAuthorizationOverride(static fn (BaseEntity|BaseResource $object) => 'cli' === strtolower(PHP_SAPI) && 'test' !== $_ENV['APP_ENV']);

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
If `setAuthorizationOverride` closure is set, it will be called with current object (resource or entity) and if it returns true, authorization will be skipped.
 
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
- In any Resource, if you have defined its grant as LIMITED, you must add attribute to BaseResource class, to define access resolver configurations for each of the resource classes
```php
#[AuthorizeResource(accessResolvers: [
    new AccessResolverConfiguration(className: OwnerPropertyAccessResolver::class, config: ['ownerPropertyPath' => 'supervisor']),
])]
```

Same class must also set following property with correct normalization group:

```php
    #[Groups('deal_read')]
    #[ApiProperty(attributes: ["openapi_context" => ["description" => "If Authorization GrantType::OWN or GROUP is calculated, resource can be restricted."]])]
    public bool $isRestricted = false;
```
### Property visibility check
Sometimes you want to return all items in endpoint but want to limit properties returned based on user roles. To do so, you need to set `GrantType::LIMITED` to
role and operation you want to have this visibility check and add `#[VisibleProperty]` attribute to resource where this check should be done. 
`#[VisibleProperty]` attribute takes 2 parameters: `ownerProperty` and `properties`. `properties` is an array of all properties you want to `SHOW`. `ownerProperty` is
the name of property which to check against current logged in user.  
> **IMPORTANT**: If resource has GrantType::LIMITED to some role for get or get_collection operations, at least one access resolver or `#[VisibleProperty]` must be set!

### Explicit check if all roles are configured in authorization service

If you want explicitly check if all project defined roles are fully configured in authorization service, you can configure this check by passing BackedEnum containing
all needed roles to configuration.
> Default value is `[]`, so without this configuration check will not be triggered.  
```php
<?php declare(strict_types = 1);

use App\Constants\Enum\Roles;
use Symfony\Config\EntityResourceMapperConfig;

return static function (EntityResourceMapperConfig $config): void {
    $config
        ->rolesEnum(Roles::class);
};
```
or
```yaml
entity_resource_mapper:
    roles_enum: App\Constants\Enum\Roles
```
This enum must be backed and contain all needed roles with `ROLE_` prefix like this:
```php
<?php declare(strict_types = 1);

namespace App\Constants\Enum;

enum Roles: string
{
    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN'
}
```
Now if you don't have ROLE_USER or ROLE_ADMIN grants configured for any resource operation you passed in `AuthorizationService->setServices()`, exception will be thrown.

### Public resource access ###

If it is required to access any resource without authorization (by default this is forbidden), you can use `AuthorizationServiceConfigurator` 
to allow specific operations for `AuthenticatedVoter::PUBLIC_ACCESS`. To do so, configure needed operations using `GrantType::ALL`. Only
`GrantType::ALL` is allowed to be used (no option for `GrantType::LIMITED`) and you do not need to set `GrantType::NONE` for public 
access.
Example:
```php
// src/Service/Configurator/AuthorizationServiceConfigurator.php

use WhiteDigital\EntityResourceMapper\Security\AuthorizationServiceConfiguratorInterface;
use WhiteDigital\EntityResourceMapper\Security\Enum\GrantType;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;

final class AuthorizationServiceConfigurator implements AuthorizationServiceConfiguratorInterface
{
    public function __invoke(AuthorizationService $service): void
    {
        $service->setResources([
            ActivityResource::class => [
                AuthorizationService::ALL => ['ROLE_SUPER_ADMIN' => GrantType::ALL, 'ROLE_KAM' => GrantType::ALL],
                AuthorizationService::COL_GET => ['ROLE_JUNIOR_KAM' => GrantType::LIMITED],
                AuthorizationService::ITEM_GET => [AuthenticatedVoter::PUBLIC_ACCESS => GrantType::ALL],
                AuthorizationService::COL_POST => [],
                AuthorizationService::ITEM_PATCH => [],
                AuthorizationService::ITEM_DELETE => [],
            ]]);
    }
}
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

### Base provider and processor
In most cases way how to read or write data to database is the same, so this package provides `AbstractDataProcessor` 
and `AbstractDataProvider` that implements base logic for api platform.
Maker part of this package uses these clases for generation as well. Using these abstractions will take away need to
duplicate code for each entity/resource. As these are abstractions, you can always override any function of them when
needed.

### Extended api resource
Other `whitedigital-eu` packages may come with api resources that with some configuration may not be suited for
straight away usage in a project. This is why `ExtendedApiResource` is useful to override part of options defined
in default attributes.

For example, take a look at `WhiteDigital\Audit\ApiResource\AuditResource` class. It defines api resource. 
If you want iri to be `/api/vendor/audits`, you have to do the following:
1. Create new class that extends resource you want to override
2. Add `ExtendedApiResouce` attribute insted of `ApiResource` attribute
3. Pass only those options that you want to override, others will be taken from resource you are extending
```php
namespace App\ApiResource;

use WhiteDigital\EntityResourceMapper\Attribute\ExtendedApiResource;

#[ExtendedApiResource(routePrefix: '/vendor')]
class AuditResource extends WhiteDigital\Audit\ApiResource\AuditResource
{
}
```
`ExtendedApiResouce` attribute checks which resource you are extending and overrides options given in extension,
keeping other options same as in parent resource.

> **IMPORTANT**: You need to disable bundled resource using api_platform.openapi.factory decorator, otherwise you will have 2 instances of audit
> resource: one with `/api/audits` iri and one with `/api/vendor/audits` iri.

### ApiResource maker
Default configuration options comes based on `api-platform`|`symfony` recommendations but you can override them like this (default values shown):
```yaml
api_resource:
    namespaces:
        api_resource: ApiResource
        class_map_configurator: Service\\Configurator # required by whitedigital-eu/entity-resource-mapper-bundle
        data_processor: DataProcessor
        data_provider: DataProvider
        entity: Entity
        root: App
    defaults:
        api_resource_suffix: Resource
        role_separator: ':'
        space: '_'
```
```php
use Symfony\Config\EntityResourceMapperConfig;

return static function (EntityResourceMapperConfig $config): void {
    $namespaces = $config
        ->namespaces();

    $namespaces
        ->apiResource('ApiResource')
        ->classMapConfigurator('Service\\Configurator') # required by whitedigital-eu/entity-resource-mapper-bundle
        ->dataProcessor('DataProcessor')
        ->dataProvider('DataProvider')
        ->entity('Entity')
        ->root('App');
        
    $defaults = $config
        ->defaults();
        
    $defaults
        ->apiResourceSuffix('Resource')
        ->roleSeparator(':')
        ->space('_');
};
```
`namespaces` are there to set up different directories for generated files. So, if you need to put files in different directories/namespaces, you can chnage it as such.

`roleSeparator` and `space` from `defaults` are added to configure separators for groups used in api resource. For example, `UserRole` with defaults will become `user_role:read` for read group.  
`apiResourcrSuffix` defines suffix for api resource class name. For example, by default `User` entity will make `UserResource` api resource class.

### Usage
Simply run `make:api-resource <EntityName>` where EntityName is entity you want to create api resource for.
Example, `make:api-resource User` to make UserResource, UserDataProcessor and UserDataProvider for User entity.

Maker command generates resource properties based on entity variables. This could sometimes be incorrect or not needed, so you can pass `--no-properties` option to not generate properties.  
By default, maker command will throw an error if you are trying to generate classes that already exist. If for some reason you want to rewrite generated classes, you can pass `--delete-if-exists`
option.  
This option comes in handy on occasion when you have 2 entities, that have relation. Because of specific logical impossibility, to generate resources for both classes automatically, you should:  
1. run `make:api-resource Entity1 --no-properties`  
2. run `make:api-resource Entity2`  
3. run `make:api-resource Entity1 --delete-if-exists`

This command automatically generates ApiFilters for given entity. Default value is to generate them is for first level fields. Like this:

```php
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use WhiteDigital\EntityResourceMapper\Filters\ResourceDateFilter;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[
    ApiResource (
        shortName: 'User'
    ),
    ApiFilter(ResourceDateFilter::class, properties: ['createdAt', 'updatedAt', ]),
]
class UserResource extends BaseResource 
{
    public ?DateTimeImmutable $createdAt = null;

    public ?DateTimeImmutable $updatedAt = null;
    
    public ?UserResource $parent = null;
}
```
If you don't want to generate any filters, run command by passing `level 0`:  
```shell
bin/console make:api-resource User --level 0
```

If you want generate filters for more levels for subresources, like, parent.createdAt, pass higher level:  
```shell
bin/console make:api-resource User --level 2
```
```php
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use WhiteDigital\EntityResourceMapper\Filters\ResourceDateFilter;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[
    ApiResource (
        shortName: 'User'
    ),
    ApiFilter(ResourceDateFilter::class, properties: ['createdAt', 'updatedAt', 'parent.createdAt', 'parent.updatedAt']),
]
class UserResource extends BaseResource 
{
    public ?DateTimeImmutable $createdAt = null;

    public ?DateTimeImmutable $updatedAt = null;
    
    public ?UserResource $parent = null;
}
```
Higher level -> deeper subresource filters.  
It is obvious that you probably do not need all generated filters, but it is easier to remove than it is to add.  

If you want to exclude specific type of filters, you can pass `--exclude-<filter>` to skip generation of those filters.  
```shell
bin/console make:api-resource User --level 2 --exclude-array --exclude-numeric --exclude-range
```
Available filters are:  
+ `array`: `ResourceJsonFilter`
+ `bool`: `ResourceBooleanFilter`
+ `date`: `ResourceDateFilter`
+ `enum`: `ResourceEnumFilter`
+ `numeric`: `ResourceNumericFilter`
+ `range`: `ResourceRangeFilter`
+ `search`: `ResourceSearchFilter`

`ResourceOrderFilter` is created from non-excluded `numeric`, `search`, `date` and `array` filters.  

### PHP CS Fixer
> **IMPORTANT**: When running php-cs-fixer, make sure not to format files in `skeleton` folder. Otherwise maker
> command will stop working.

### Validators
This library contains validators for Classifiers. These will only work if Entity/Resource have following structure (not less than this):
Entity:

```php
use Doctrine\ORM\Mapping\Entity;

#[Entity]
class Classifier
{
    private ?int $id = null;
    private ?string $value = null;
    private ?array $data = [];
    private ?ClassifierType $type = null;
}
```
Resource:
```php
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class ClassifierResource
{
    public mixed $id = null;
    public ?string $value = null;
    public ?array $data = [];
    public ?ClassifierType $type = null;
}
```
As seen in these examples, you need to have a Backed enum (here called `ClassifierType`) that you need to validate against.  
Example of `ClassifierType` is something like this:
```php
enum ClassifierType: string
{
    case ONE = 'ONE';
    case TWO = 'TWO';
}
```
Now you can use either `CorrectClassifierType` or `ClassifierRequiredDataIsSet` validator:  

---

`CorrectClassifierType`:
CorrectClassifierType checks if in related resource Classifier is given with correct type:

```php
use ApiPlatform\Metadata\ApiResource;
use WhiteDigital\EntityResourceMapper\Validator\Constraints as WDAssert;

#[ApiResource]
class TestResource
{
    #[WDAssert\CorrectClassifierType(ClassifierType::ONE)]
    public ?ClassifierResource $one = null;
}
```
Now if you pass resource that has any other type (like `ClassifierType::TWO` for example) to this resource, error will be thrown.  

---

`ClassifierRequiredDataIsSet`:
Sometimes you may need extra data in Classifier and may be mandatory. For this ClassifierRequiredDataIsSet can be used to check if this 
data is passed. This is used on `ClassifierResource`:

```php
use ApiPlatform\Metadata\ApiResource;
use WhiteDigital\EntityResourceMapper\Validator\Constraints as WDAssert;

#[ApiResource]
#[WDAssert\ClassifierRequiredDataIsSet(ClassifierType::ONE, ['test1'])]
class ClassifierResource
{
    public mixed $id = null;
    public ?string $value = null;
    public ?array $data = [];
    public ?ClassifierType $type = null;
}
```
And now when creating a new Classifier with type `ONE`, an error will be thrown if data does not contain value with key `test1`

## Tests

Run tests by:
```bash
$ vendor/bin/phpunit
```

## TODO ##
- performance improvements
- explicit joins on dataprovider
- computed properties as querybuilder methods
