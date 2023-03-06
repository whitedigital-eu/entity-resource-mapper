<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Attribute;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\State\OptionsInterface;
use Attribute;
use InvalidArgumentException;
use PhpToken;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function count;
use function debug_backtrace;
use function file_get_contents;
use function func_get_args;
use function sprintf;

use const T_CLASS;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_STRING;
use const T_WHITESPACE;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ExtendedApiResource extends ApiResource
{
    /**
     * @throws ReflectionException
     */
    public function __construct(?string $uriTemplate = null, ?string $shortName = null, ?string $description = null, string|array|null $types = null, $operations = null, $formats = null, $inputFormats = null, $outputFormats = null, $uriVariables = null, ?string $routePrefix = null, ?array $defaults = null, ?array $requirements = null, ?array $options = null, ?bool $stateless = null, ?string $sunset = null, ?string $acceptPatch = null, ?int $status = null, ?string $host = null, ?array $schemes = null, ?string $condition = null, ?string $controller = null, ?string $class = null, ?int $urlGenerationStrategy = null, ?string $deprecationReason = null, ?array $cacheHeaders = null, ?array $normalizationContext = null, ?array $denormalizationContext = null, ?bool $collectDenormalizationErrors = null, ?array $hydraContext = null, ?array $openapiContext = null, bool|Operation|null $openapi = null, ?array $validationContext = null, ?array $filters = null, ?bool $elasticsearch = null, $mercure = null, $messenger = null, $input = null, $output = null, ?array $order = null, ?bool $fetchPartial = null, ?bool $forceEager = null, ?bool $paginationClientEnabled = null, ?bool $paginationClientItemsPerPage = null, ?bool $paginationClientPartial = null, ?array $paginationViaCursor = null, ?bool $paginationEnabled = null, ?bool $paginationFetchJoinCollection = null, ?bool $paginationUseOutputWalkers = null, ?int $paginationItemsPerPage = null, ?int $paginationMaximumItemsPerPage = null, ?bool $paginationPartial = null, ?string $paginationType = null, ?string $security = null, ?string $securityMessage = null, ?string $securityPostDenormalize = null, ?string $securityPostDenormalizeMessage = null, ?string $securityPostValidation = null, ?string $securityPostValidationMessage = null, ?bool $compositeIdentifier = null, ?array $exceptionToStatus = null, ?bool $queryParameterValidationEnabled = null, ?array $graphQlOperations = null, $provider = null, $processor = null, ?OptionsInterface $stateOptions = null, array $extraProperties = [])
    {
        $callerClass = $this->getCallerClass(file: debug_backtrace()[0]['file'] ?? null);
        $attributes = null;

        try {
            $caller = new ReflectionClass(objectOrClass: $callerClass);
            $parent = $caller->getParentClass();

            if (false === $parent) {
                throw new InvalidArgumentException(sprintf('%s must only be used with parent class, no parent found on %s', __CLASS__, $callerClass));
            }

            $resource = $parent->getAttributes(name: ApiResource::class);
            if ([] === $resource) {
                throw new InvalidArgumentException(sprintf('%s must only be used to extend %s attribute, no such attrbute found on %s', __CLASS__, ApiResource::class, $callerClass));
            }

            $attributes = $resource[0]->getArguments();
            $current = (new ReflectionClass(objectOrClass: __CLASS__))->getMethod(name: __FUNCTION__);
            $i = 0;
            $args = func_get_args();

            foreach ($current->getParameters() as $parameter) {
                if (isset($args[$i]) && $parameter->getDefaultValue() !== $args[$i]) {
                    $attributes[$parameter->getName()] = $args[$i];
                }
                $i++;
            }
        } catch (ReflectionException) {
        }

        if (null === $attributes) {
            throw new InvalidConfigurationException(sprintf('Unable to extend %s in %s', self::class, $callerClass));
        }

        parent::__construct(...$attributes);
    }

    private function getCallerClass(?string $file): ?string
    {
        if (null === $file) {
            return null;
        }

        $namespace = '';
        $tokens = PhpToken::tokenize(file_get_contents($file));

        for ($i = 0, $c = count($tokens); $i < $c; $i++) {
            if (T_NAMESPACE === $tokens[$i]->id) {
                for ($j = $i + 1; $j < $c; $j++) {
                    if (T_NAME_QUALIFIED === $tokens[$j]->id) {
                        $namespace = $tokens[$j]->text;
                        break;
                    }
                }
            }

            if (T_CLASS === $tokens[$i]->id) {
                for ($j = $i + 1; $j < $c; $j++) {
                    if (T_WHITESPACE === $tokens[$j]->id) {
                        continue;
                    }

                    if (T_STRING === $tokens[$j]->id) {
                        return $namespace . '\\' . $tokens[$j]->text;
                    }
                    break;
                }
            }
        }

        return null;
    }
}
