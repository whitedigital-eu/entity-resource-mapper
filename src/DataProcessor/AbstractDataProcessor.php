<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DataProcessor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Security\AuthorizationService;
use WhiteDigital\EntityResourceMapper\Security\Enum\GrantType;

use function array_key_exists;
use function array_merge;
use function preg_match;

abstract class AbstractDataProcessor implements ProcessorInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ResourceToEntityMapper $resourceToEntityMapper,
        protected readonly EntityToResourceMapper $entityToResourceMapper,
        protected readonly AuthorizationService $authorizationService,
        protected readonly ParameterBagInterface $bag,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        if (!$operation instanceof DeleteOperationInterface) {
            if ($operation instanceof Patch) {
                $entity = $this->patch($data, $operation, $context);
            } else {
                $entity = $this->post($data, $operation, $context);
            }

            $this->flushAndRefresh($entity);

            return $this->createResource($entity, $context);
        }

        $this->remove($data, $operation);

        return null;
    }

    protected function patch(mixed $data, Operation $operation, array $context = []): ?BaseEntity
    {
        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::ITEM_PATCH, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($data, AuthorizationService::ITEM_PATCH);
        $existingEntity = $this->findById($this->getEntityClass(), $data->id);

        return $this->createEntity($data, $context, $existingEntity);
    }

    protected function post(mixed $data, Operation $operation, array $context = []): ?BaseEntity
    {
        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::COL_POST, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($data, AuthorizationService::COL_POST);

        return $this->createEntity($data, $context);
    }

    abstract protected function createEntity(BaseResource $resource, array $context, ?BaseEntity $existingEntity = null);

    protected function findById(string $class, int $id): ?BaseEntity
    {
        return $this->entityManager->getRepository($class)->find($id);
    }

    abstract protected function getEntityClass(): string;

    protected function flushAndRefresh(BaseEntity $entity): void
    {
        $this->entityManager->persist($entity);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            preg_match('/DETAIL: (.*)/', $exception->getMessage(), $matches);
            throw new PreconditionFailedHttpException($this->translator->trans('record_already_exists', ['%detail%' => $matches[1]], domain: 'ApiResource'), $exception);
        }

        $this->entityManager->refresh($entity);
    }

    abstract protected function createResource(BaseEntity $entity, array $context);

    protected function override(string $operation, string $class): bool
    {
        try {
            $property = (new ReflectionClass($this->authorizationService))->getProperty('resources')->getValue($this->authorizationService);
        } catch (ReflectionException) {
            return false;
        }

        if (isset($property[$class])) {
            $attributes = $property[$class];
        } else {
            return false;
        }

        $allowed = array_merge($attributes[AuthorizationService::ALL] ?? [], $attributes[$operation] ?? []);
        if ([] !== $allowed && array_key_exists(AuthenticatedVoter::PUBLIC_ACCESS, $allowed)) {
            if (GrantType::ALL === $allowed[AuthenticatedVoter::PUBLIC_ACCESS]) {
                return true;
            }

            throw new InvalidConfigurationException('Public access only allowed with "all" grant type');
        }

        return false;
    }

    protected function remove(BaseResource $resource, Operation $operation): void
    {
        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::ITEM_DELETE, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($resource, AuthorizationService::ITEM_DELETE);
        $entity = $this->findById($this->getEntityClass(), $resource->id);
        if (null !== $entity) {
            $this->removeWithFkCheck($entity);
        }
    }

    protected function removeWithFkCheck(BaseEntity $entity): void
    {
        $this->entityManager->remove($entity);

        try {
            $this->entityManager->flush();
        } catch (Exception $exception) {
            preg_match('/DETAIL: (.*)/', $exception->getMessage(), $matches);
            throw new AccessDeniedHttpException($this->translator->trans('unable_to_delete_record', ['%detail%' => $matches[1]], domain: 'ApiResource'), $exception);
        }
    }
}
