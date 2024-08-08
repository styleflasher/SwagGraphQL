<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Doctrine\Inflector\Inflector;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use SwagGraphQL\Factory\InflectorFactory;
use SwagGraphQL\Resolver\Struct\ConnectionStruct;
use SwagGraphQL\Resolver\Struct\EdgeStruct;
use SwagGraphQL\Resolver\Struct\PageInfoStruct;
use SwagGraphQL\Schema\Mutation;
use Symfony\Component\DependencyInjection\ContainerInterface;

//@TODO: simply this class and QueryResolver (both are almost the same)
class SalesChannelQueryResolver
{
    private readonly Inflector $inflector;

    public function __construct(private readonly ContainerInterface $container, private readonly DefinitionInstanceRegistry $DefinitionInstanceRegistry, InflectorFactory $inflectorFactory)
    {
        $this->inflector = $inflectorFactory->getInflector();
    }

    /**
     * Default Resolver
     * uses the library provided defaultResolver for meta Fields
     * and the resolveQuery() and resolveMutation() function for Query and Mutation Fields
     */
    public function resolve($rootValue, $args, $context, ResolveInfo $info)
    {
        $path = $info->path[0];
        if (is_array($path)) {
            $path = $path[0];
        }

        try {
            if (str_starts_with($path, '__')) {
                return Executor::defaultFieldResolver($rootValue, $args, $context, $info);
            }
            if ($info->operation->operation !== 'mutation') {
                return $this->resolveQuery($rootValue, $args, $context, $info);
            }

            return $this->resolveMutation($rootValue, $args, $context, $info);
        } catch (\Throwable $e) {
            // default error-handler will just show "internal server error"
            // therefore throw own Exception
            throw new QueryResolvingException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolver for Query queries
     * On the Root-Level it searches for the Entity with th given Args
     * On non Root-Level it returns the get-Value of the Field
     * @param $rootValue
     * @param $args
     * @param SalesChannelContext $context
     * @param ResolveInfo $info
     * @return 0|mixed|ConnectionStruct|null
     * @throws DefinitionNotFoundException
     */
    private function resolveQuery($rootValue, $args, $context, ResolveInfo $info)
    {
        if ($rootValue === null) {
            $entityName = $this->inflector->singularize($info->fieldName);
            $definition = $this->DefinitionInstanceRegistry->get($this->inflector->tableize($entityName));
            $repo = $this->getRepository($definition);

            $criteria = CriteriaParser::buildCriteria($args, $definition);

            $associationFields = $definition->getFields()->filterInstance(OneToManyAssociationField::class);
            AssociationResolver::addAssociations($criteria, $info->lookahead()->queryPlan(), $definition);

            if ($definition === CustomerDefinition::class) {
                if ($context->getCustomer() === null) {
                    throw new CustomerNotLoggedInException();
                }

                $criteria->addFilter(new EqualsFilter('id', $context->getCustomer()->getId()));
            } else {

                /** @var OneToManyAssociationField $associationField */
                foreach ($associationFields->getElements() as $associationField) {
                    if ($associationField->getReferenceClass() === SalesChannelDefinition::class) {
                        $criteria->addFilter(new EqualsFilter($definition->getEntityName() . '.salesChannels.id', $context->getSalesChannel()->getId()));
                    }
                }
            }

            $searchResult = $repo->search($criteria, $context->getContext());

            if ($entityName !== $info->fieldName) {
                return ConnectionStruct::fromResult($searchResult);
            } else {
                return $searchResult->getEntities()->first();
            }
        }

        return $this->getSimpleValue($rootValue, $info);
    }

    /**
     * Resolver for Mutation queries
     * On the Root-Level it checks the action and calls the according function
     * On non Root-Level it returns the get-Value of the Field
     */
    private function resolveMutation($rootValue, $args, $context, ResolveInfo $info)
    {
        if ($rootValue === null) {
            $mutation = Mutation::fromName($info->fieldName);

            switch ($mutation->getAction()) {
                case Mutation::ACTION_CREATE:
                    return $this->create($args, $context, $info, $mutation->getEntityName());
                case Mutation::ACTION_UPDATE:
                    return $this->update($args, $context, $info, $mutation->getEntityName());
                case Mutation::ACTION_DELETE:
                    return $this->delete($args, $context, $mutation->getEntityName());
            }
        }

        return $this->getSimpleValue($rootValue, $info);
    }

    /**
     * Creates and returns the entity
     */
    private function create($args, $context, ResolveInfo $info, string $entity): Entity
    {
        $definition = $this->DefinitionInstanceRegistry->get($entity);
        $billingAddressId = Uuid::randomHex();

        $args = array_merge_recursive($args, [
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'languageId' => $context->getContext()->getLanguageId(),
            'groupId' => $context->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'billingAddress' => $billingAddressId,
            'shippingMethod'
        ]);

        $repo = $this->getRepository($definition);

        $event = $repo->create([$args], $context->getContext());
        $id = $event->getEventByDefinition($definition)->getIds()[0];

        $criteria = new Criteria([$id]);
        AssociationResolver::addAssociations($criteria, $info->lookahead()->queryPlan(), $definition);

        return $repo->search($criteria, $context->getContext())->get($id);
    }

    /**
     * Update and returns the entity
     */
    private function update($args, $context, ResolveInfo $info, string $entity): Entity
    {
        $definition = $this->DefinitionInstanceRegistry->get($entity);
        $repo = $this->getRepository($definition);

        $event = $repo->update([$args], $context->getContext());
        $id = $event->getEventByDefinition($definition)->getIds()[0];

        $criteria = new Criteria([$id]);
        AssociationResolver::addAssociations($criteria, $info->lookahead()->queryPlan(), $definition);

        return $repo->search($criteria, $context->getContext())->get($id);
    }

    /**
     * Deletes the entity and returns its ID
     */
    private function delete($args, $context, string $entity): string
    {
        $definition = $this->DefinitionInstanceRegistry->get($entity);
        $repo = $this->getRepository($definition);

        $event = $repo->delete([$args], $context->getContext());
        $id = $event->getEventByDefinition($definition)->getIds()[0];

        return $id;
    }

    private function getRepository(EntityDefinition $definition): EntityRepository
    {
        $repositoryClass = $definition->getEntityName() . '.repository';

        if ($this->container->has($repositoryClass) === false) {
            throw new \Exception('Repository not found: ' . $definition->getEntityName());
        }

        /** @var EntityRepository $repo */
        $repo = $this->container->get($definition->getEntityName() . '.repository');

        return $repo;
    }

    private function wrapConnectionType(array $elements): ConnectionStruct
    {
        return (new ConnectionStruct())->assign([
            'edges' => EdgeStruct::fromElements($elements, 0),
            'total' => count($elements),
            'pageInfo' => new PageInfoStruct()
        ]);
    }

    private function getSimpleValue($rootValue, ResolveInfo $info)
    {
        $result = null;
        $getter = 'get' . ucfirst($info->fieldName);
        if (method_exists($rootValue, $getter)) {
            $result = $rootValue->$getter();
        }
        if (is_array($rootValue) && array_key_exists($info->fieldName, $rootValue)) {
            $result = $rootValue[$info->fieldName];
        }

        if ($result instanceof EntityCollection) {
            // ToDo handle args in connections
            return $this->wrapConnectionType($result->getElements());
        }

        return $result;
    }

}
