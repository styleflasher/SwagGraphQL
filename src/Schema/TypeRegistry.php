<?php declare(strict_types=1);

namespace SwagGraphQL\Schema;


use Doctrine\Inflector\Inflector;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Factory\InflectorFactory;
use SwagGraphQL\Resolver\QueryResolvingException;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilder;
use SwagGraphQL\Schema\SchemaBuilder\ObjectBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TypeRegistry
{
    private array $types = [];

    /** @var InputObjectType[] */
    private array $inputTypes = [];

    private readonly Inflector $inflector;

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly CustomTypes $customTypes,
        #[Autowire(service: 'swag_graphql.query_registry')]
        private readonly CustomFieldRegistry $queries,
        #[Autowire(service: 'swag_graphql.mutation_registry')]
        private readonly CustomFieldRegistry $mutations,
        #[Autowire(service: 'swag_graphql.sales_channel_query_registry')]
        private readonly CustomFieldRegistry $salesChannelQueries,
        #[Autowire(service: 'swag_graphql.sales_channel_mutation_registry')]
        private readonly CustomFieldRegistry $salesChannelMutations,
        private readonly LanguageDefinition $languageDefinition,
        private readonly CountryDefinition $countryDefinition,
        private readonly CurrencyDefinition $currencyDefinition,
        private readonly PaymentMethodDefinition $paymentMethodDefinition,
        private readonly ShippingMethodDefinition $shippingMethodDefinition,
        private readonly ProductDefinition $productDefinition,
        private readonly CategoryDefinition $categoryDefinition,
        private readonly SalutationDefinition $salutationDefinition,
        private readonly CustomerDefinition $customerDefinition,
        InflectorFactory $inflectorFactory
    ) {
        $this->inflector = $inflectorFactory->getInflector();
    }

    /**
     * @param string|EntityDefinition $definition
     * @return ObjectType
     */
    public function getObjectForDefinition(EntityDefinition|string $definition): ObjectType
    {
        if (is_string($definition)) {
            $definition = new $definition();
            $definition->compile($this->definitionInstanceRegistry);
        }

        if (!isset($this->types[$definition->getEntityName()])) {
            $this->types[$definition->getEntityName()] =
                ObjectBuilder::create($this->inflector->classify($definition->getEntityName()))
                ->addLazyFieldCollection(fn() => $this->getFieldsForDefinition($definition))
                ->build();
        }

        return $this->types[$definition->getEntityName()];
    }

    public function getQuery(): ObjectType
    {
        $query = ObjectBuilder::create('Query');
        foreach ($this->definitionInstanceRegistry->getDefinitions() as $definition) {
            if ($this->isTranslationDefinition($definition) || $this->isMappingDefinition($definition)) {
                continue;
            }

            $this->addFieldsForDefinition($definition, $query);
        }
        return $query
            ->addLazyFieldCollection(fn() => $this->customFields($this->queries))
            ->build();
    }

    public function getSalesChannelQuery(): ObjectType
    {
        $query = ObjectBuilder::create('Query');

        $this->addFieldsForDefinition($this->languageDefinition, $query);
        $this->addFieldsForDefinition($this->countryDefinition, $query);
        $this->addFieldsForDefinition($this->customerDefinition, $query);
        $this->addFieldsForDefinition($this->paymentMethodDefinition, $query);
        $this->addFieldsForDefinition($this->shippingMethodDefinition, $query);
        $this->addFieldsForDefinition($this->productDefinition, $query);
        $this->addFieldsForDefinition($this->categoryDefinition, $query);
        $this->addFieldsForDefinition($this->salutationDefinition, $query);
        $this->addFieldsForDefinition($this->currencyDefinition, $query);
        $query->addField(
            FieldBuilder::create(
                $this->customerDefinition->getEntityName(),
                $this->getObjectForDefinition($this->customerDefinition)
            ));

        return $query
            ->addLazyFieldCollection(fn() => $this->customFields($this->salesChannelQueries))
            ->build();
    }

    public function getMutation(): ObjectType
    {
        $mutation = ObjectBuilder::create('Mutation');
        foreach ($this->definitionInstanceRegistry->getDefinitions() as $definition) {
            if ($this->isTranslationDefinition($definition) || $this->isMappingDefinition($definition)) {
                continue;
            }
            $createName = new Mutation(Mutation::ACTION_CREATE, $definition->getEntityName());
            $mutation->addField(
                FieldBuilder::create($createName->getName(), $this->getObjectForDefinition($definition->getClass()))
                    ->setArguments($this->getInputFieldsForCreate($definition))
            );

            $updateName = new Mutation(Mutation::ACTION_UPDATE, $definition->getEntityName());
            $mutation->addField(
                FieldBuilder::create($updateName->getName(), $this->getObjectForDefinition($definition->getClass()))
                    ->setArguments($this->getInputFieldsForUpdate($definition))
            );

            $deleteName = new Mutation(Mutation::ACTION_DELETE, $definition->getEntityName());
            $mutation->addField(
                FieldBuilder::create($deleteName->getName(), Type::id())
                    ->setArguments($this->getPrimaryKeyArgs($definition))
            );
        }

        return $mutation
            ->addLazyFieldCollection(fn() => $this->customFields($this->mutations))
            ->build();
    }

    public function getSalesChannelMutation(): ObjectType
    {
        $mutation = ObjectBuilder::create('Mutation');

        $createName = new Mutation(Mutation::ACTION_CREATE, $this->customerDefinition->getEntityName());
        $mutation->addField(
            FieldBuilder::create($createName->getName(), $this->getObjectForDefinition($this->customerDefinition))
                ->setArguments($this->getInputFieldsForCreate($this->customerDefinition))
        );

        $updateName = new Mutation(Mutation::ACTION_UPDATE, $this->customerDefinition->getEntityName());
        $mutation->addField(
            FieldBuilder::create($updateName->getName(), $this->getObjectForDefinition($this->customerDefinition))
                ->setArguments(
                    $this->getInputFieldsForDefinition($this->customerDefinition, fn($type) => $type)
                ));

        return $mutation
            ->addLazyFieldCollection(fn() => $this->customFields($this->salesChannelMutations))
            ->build();
    }

    private function isTranslationDefinition($definition): bool
    {
        return str_contains((string) $definition->getEntityName(), '_translation');
    }

    private function isMappingDefinition($definition): bool
    {
        $instance = new $definition();
        return $instance instanceof MappingEntityDefinition;
    }

    private function getInputForDefinition(EntityDefinition $definition): InputObjectType
    {
        if (!isset($this->inputTypes[$definition->getEntityName()])) {
            $this->inputTypes[$definition->getEntityName()] =
                ObjectBuilder::create('Input' . $this->inflector->classify($definition->getEntityName()))
                ->addLazyFieldCollection(fn() => $this->getInputFieldsForDefinition($definition))
                ->buildAsInput();
        }

        return $this->inputTypes[$definition->getEntityName()];
    }

    private function getConnectionTypeForDefinition(EntityDefinition $definition): ObjectType
    {
        if (!isset($this->types[$definition->getEntityName() . '_connection'])) {

            $this->types[$definition->getEntityName() . '_connection'] =
                ObjectBuilder::create($this->inflector->classify($definition->getEntityName()) . 'Connection')
                ->addField(FieldBuilder::create('total', Type::int())->setDescription('The total of Items found by the Query'))
                ->addField(FieldBuilder::create('edges', $this->getEdgeTypeForDefinition($definition))->setDescription('A List of the Items'))
                ->addField(FieldBuilder::create('pageInfo', $this->customTypes->pageInfo())->setDescription('Additional information for pagination'))
                ->addField(FieldBuilder::create('aggregations', Type::listOf($this->customTypes->aggregationResult()))->setDescription('the result of aggregations'))
                ->addLazyFieldCollection(fn() => $this->getFieldsForDefinition($definition))
                ->setDescription('The Result for a search that returns multiple Items')
                ->build();
        }

        return $this->types[$definition->getEntityName() . '_connection'];
    }

    private function getEdgeTypeForDefinition(EntityDefinition $definition): ListOfType
    {
        if (!isset($this->types[$definition->getEntityName() . '_edge'])) {
            $this->types[$definition->getEntityName() . '_edge'] = Type::listOf(
                ObjectBuilder::create($this->inflector->classify($definition->getEntityName()) . 'Edge')
                ->addField(FieldBuilder::create('node', $this->getObjectForDefinition($definition->getClass()))->setDescription('The Node of the Edge that contains the real element'))
                ->addField(FieldBuilder::create('cursor', Type::id())->setDescription('The cursor to the Item of the Edge'))
                ->setDescription('Contains the information for one Edge')
                ->build()
            );
        }

        return $this->types[$definition->getEntityName() . '_edge'];
    }

    private function getConnectionArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField('first', Type::int(), 'The count of items to be returned')
            ->addField('last', Type::int(), 'The count of items to be returned')
            ->addField('after', Type::string(), 'The cursor to the first Result to be fetched')
            ->addField('before', Type::string(), 'The cursor to the last Result to be fetched')
            ->addField('sortBy', Type::string(), 'The field used for sorting')
            ->addField('sortDirection', $this->customTypes->sortDirection(), 'The direction of the sorting')
            ->addField('query', $this->customTypes->query(), 'The query the DAL should perform')
            ->addField('aggregations', Type::listOf($this->customTypes->aggregation()), 'The aggregations should perform');
    }

    private function getFieldsForDefinition(EntityDefinition $definition): FieldBuilderCollection
    {
        $fields = FieldBuilderCollection::create();
        foreach ($definition->getFields() as $field) {
            $type = $this->getFieldType($field);
            if ($type instanceof Type) {
                $field = FieldBuilder::create($field->getPropertyName(), $type);

                $name = $type->toString();
                if ($name && str_ends_with($name, 'Connection')) {
                     $field->setArguments($this->getConnectionArgs());
                }

                $fields->addFieldBuilder($field);
            }
        }

        return $fields;
    }

    private function getPrimaryKeyArgs(EntityDefinition $definition): FieldBuilderCollection
    {
        $args = FieldBuilderCollection::create();
        foreach ($definition->getFields()->filterByFlag(PrimaryKey::class) as $field) {
            /** @var ObjectType|ScalarType|InputObjectType|ListOfType|null $type */
            $type = $this->getFieldType($field, true);
            if ($type) {
                if (!$field instanceof VersionField) {
                    $type = Type::nonNull($type);
                }
                $args->addField($field->getPropertyName(), $type);
            }
        }

        return $args;
    }

    private function getInputFieldsForDefinition(
        EntityDefinition $definition,
        \Closure $typeModifier = null,
        bool $withDefaults = false
    ): FieldBuilderCollection
    {
        $fields = FieldBuilderCollection::create();
        $defaults = $definition->getDefaults();
        /** @var Field $field */
        foreach ($definition->getFields() as $field) {
            $type = $this->getFieldType($field, true);
            if ($type) {
                if ($typeModifier) {
                    $type = $typeModifier($type, $field);
                }
                $builder = FieldBuilder::create($field->getPropertyName(), $type);

                if ($withDefaults && array_key_exists($field->getPropertyName(), $defaults)) {
                        $builder->setDefault($defaults[$field->getPropertyName()]);
                }
                $fields->addFieldBuilder($builder);
            }
        }

        return $fields;
    }

    private function getInputFieldsForCreate(EntityDefinition $definition): FieldBuilderCollection
    {
        return $this->getInputFieldsForDefinition($definition, function($type, Field $field) {
            // We wrap all required Fields as NonNullable
            // Except IDs because we assume that those will be generate or come from the ID field of the association Object
            // also CreatedAt and UpdatedAt are marked as required in the DAL but they are not necessary
            if ($field->getFlag(Required::class) &&
                !$type instanceof IDType &&
                !$field instanceof UpdatedAtField &&
                !$field instanceof CreatedAtField &&
                !$field instanceof TranslationsAssociationField) {
                return Type::nonNull($type);
            }

            return $type;
        }, true);
    }

    private function getInputFieldsForUpdate(EntityDefinition $definition): FieldBuilderCollection
    {
        return $this->getInputFieldsForDefinition($definition, function($type, Field $field) {
            // we make PKs required for Update
            if ($field->getFlag(PrimaryKey::class) && !$type instanceof NonNull && !$field instanceof VersionField) {
                return Type::nonNull($type);
            }

            return $type;
        });
    }

    private function getFieldType(Field $field, bool $input = false): ?Type
    {
        $type = null;
        switch (true) {
            case $field instanceof IdField:
            case $field instanceof FkField:
                $type = Type::id();
                break;
            case $field instanceof BoolField:
                $type = Type::boolean();
                break;
            case $field instanceOf DateField:
                $type = $this->customTypes->date();
                break;
            case $field instanceof IntField:
                $type = Type::int();
                break;
            case $field instanceof FloatField:
                $type = Type::float();
                break;
//            case $field instanceof JsonField:
//            case $field instanceof ConfigJsonField:
//                $type = $this->customTypes->json(); //@TODO: check
//                break;
            case $field instanceof LongTextField:
            case $field instanceof StringField:
            case $field instanceof TranslatedField:
                $type = Type::string();
                break;
            case $field instanceof ManyToManyAssociationField:
                $type = $input ?
                    Type::listOf($this->getInputForDefinition($field->getReferenceDefinition())) :
                    $this->getConnectionTypeForDefinition($field->getReferenceDefinition());
                break;
            case $field instanceof OneToManyAssociationField:
                $type = $type = $input ?
                    Type::listOf($this->getInputForDefinition($field->getReferenceDefinition())) :
                    $this->getConnectionTypeForDefinition($field->getReferenceDefinition()); //@TODO: was converted from class to definition
                break;
            case $field instanceof ManyToOneAssociationField:
            case $field instanceof OneToOneAssociationField:
                $type = $input ?
                    $this->getInputForDefinition($field->getReferenceDefinition()) :
                    $this->getObjectForDefinition($field->getReferenceClass()); //@TODO: was converted from class to definition
                break;
            default:
                // StructField, StructCollectionField, TranslationAssociationField are not exposed
                return null;
        }

        if ((!$input) && $field->getFlag(Required::class)) {
            return Type::nonNull($type);
        }

        return $type;
    }

    private function customFields(CustomFieldRegistry $registry): FieldBuilderCollection
    {
        $fields = FieldBuilderCollection::create();
        /** @var GraphQLField $field */
        foreach ($registry->getFields() as $name => $field) {
            $fields->addFieldBuilder(
                FieldBuilder::create($name, $field->returnType())
                ->setArguments($field->defineArgs())
                ->setDescription($field->description())
                ->setResolver(function($rootValue, $args, $context, ResolveInfo $info) use ($field) {
                    try {
                        return $field->resolve($rootValue, $args, $context, $info);
                    } catch (\Throwable $e) {
                        // default error-handler will just show "internal server error"
                        // therefore throw own Exception
                        throw new QueryResolvingException($e->getMessage(), 0, $e);
                    }
                })
            );
        }

        return $fields;
    }

    private function addFieldsForDefinition(EntityDefinition $definition, ObjectBuilder $query): void
    {
        $fieldName = $this->inflector->camelize($definition->getEntityName());

        $query->addField(
            FieldBuilder::create(
                $fieldName,
                $this->getObjectForDefinition($definition->getClass())
            )
                ->setArguments($this->getPrimaryKeyArgs($definition))
        );

        $query->addField(
            FieldBuilder::create(
                $this->inflector->pluralize($fieldName),
                $this->getConnectionTypeForDefinition($definition)
            )
                ->setArguments($this->getConnectionArgs())
        );
    }
}
