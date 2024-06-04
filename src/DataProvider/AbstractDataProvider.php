<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DataProvider;

use ApiPlatform\Doctrine\Orm\Extension\FilterExtension;
use ApiPlatform\Doctrine\Orm\Extension\OrderExtension;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\EntityResourceMapper\Security\AuthorizationService;
use WhiteDigital\EntityResourceMapper\Traits\Override;

use function sprintf;
use function strtolower;

abstract class AbstractDataProvider implements ProviderInterface
{
    use Override;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ManagerRegistry $doctrine,
        protected readonly AuthorizationService $authorizationService,
        protected readonly ResourceToEntityMapper $resourceToEntityMapper,
        protected readonly EntityToResourceMapper $entityToResourceMapper,
        protected readonly ClassMapper $classMapper,
        protected readonly RequestStack $requestStack,
        protected readonly TranslatorInterface $translator,
        protected readonly Security $security,
        protected readonly ParameterBagInterface $bag,
        #[TaggedIterator('api_platform.doctrine.orm.query_extension.collection')]
        protected readonly iterable $collectionExtensions = [],
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            return $this->getCollection($operation, $context);
        }

        return $this->getItem($operation, $uriVariables['id'], $context);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getCollection(Operation $operation, array $context = []): array|object
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('e')->from($this->getEntityClass($operation, $context), 'e');

        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::COL_GET, $operation->getClass()));
        $this->authorizationService->limitGetCollection($operation->getClass(), $queryBuilder);

        return $this->applyFilterExtensionsToCollection($queryBuilder, new QueryNameGenerator(), $operation, $context);
    }

    protected function getEntityClass(Operation $operation, array $context = []): string
    {
        return $this->classMapper->byResource($operation->getClass(), context: $context);
    }

    protected function applyFilterExtensionsToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, Operation $operation, array $context = []): array|object
    {
        foreach ($this->collectionExtensions as $extension) {
            if ($extension instanceof FilterExtension
                || $extension instanceof QueryResultCollectionExtensionInterface) {
                $extension->applyToCollection($queryBuilder, $queryNameGenerator, $operation->getClass(), $operation, $context);
            }

            if ($extension instanceof OrderExtension) {
                $orderByDqlPart = $queryBuilder->getDQLPart('orderBy');
                if ([] !== $orderByDqlPart) {
                    continue;
                }

                foreach ($operation->getOrder() as $field => $direction) {
                    $queryBuilder->addOrderBy(sprintf('%s.%s', $queryBuilder->getRootAliases()[0], $field), $direction);
                }
            }

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($operation->getClass(), $operation, $context)) {
                return $extension->getResult($queryBuilder, $operation->getClass(), $operation, $context);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getItem(Operation $operation, mixed $id, array $context): object
    {
        $entity = $this->findByIdOrThrowException($operation, $id, $context);

        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::ITEM_GET, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($entity, AuthorizationService::ITEM_GET, context: $context);

        return $this->createResource($entity, $context);
    }

    abstract protected function createResource(BaseEntity $entity, array $context);

    protected function queryResult(QueryBuilder $queryBuilder): BaseEntity
    {
        $entity = $queryBuilder->getQuery()->getResult()[0] ?? null;

        $this->throwErrorIfNotExists($entity, $queryBuilder->getRootAliases()[0], $queryBuilder->getParameter('id')?->getValue());

        return $entity;
    }

    protected function findById(string $class, mixed $id): ?BaseEntity
    {
        return $this->entityManager->getRepository($class)->find($id);
    }

    /**
     * @throws ReflectionException
     */
    protected function findByIdOrThrowException(Operation $operation, mixed $id, array $context): BaseEntity
    {
        $entity = $this->entityManager->getRepository($entityClass = $this->getEntityClass($operation, $context))->find($id);
        $this->throwErrorIfNotExists($entity, strtolower((new ReflectionClass($entityClass))->getShortName()), $id);

        return $entity;
    }

    protected function throwErrorIfNotExists(mixed $result, string $rootAlias, mixed $id): void
    {
        if (null === $result) {
            throw new NotFoundHttpException($this->translator->trans('named_resource_not_found', ['%resource%' => $rootAlias, '%id%' => $id], domain: 'EntityResourceMapper'));
        }
    }
}
