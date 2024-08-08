<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\Schema;

use Doctrine\Inflector\Inflector;
use GraphQL\Type\Definition\BooleanType;
use GraphQL\Type\Definition\FloatType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\StringType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use SwagGraphQL\Factory\InflectorFactory;
use SwagGraphQL\Schema\CustomFieldRegistry;
use SwagGraphQL\Schema\CustomTypes;
use SwagGraphQL\Schema\Mutation;
use SwagGraphQL\Schema\TypeRegistry;
use SwagGraphQL\Tests\_fixtures\AssociationEntity;
use SwagGraphQL\Tests\_fixtures\BaseEntity;
use SwagGraphQL\Tests\_fixtures\ManyToManyEntity;
use SwagGraphQL\Tests\_fixtures\ManyToOneEntity;
use SwagGraphQL\Tests\_fixtures\MappingEntity;
use SwagGraphQL\Tests\Traits\SchemaTestTrait;
use SwagGraphQL\Types\DateType;
use SwagGraphQL\Types\JsonType;

class TypeRegistryTest extends TestCase
{
    use SchemaTestTrait, KernelTestBehaviour;

    /** @var MockObject */
    private $DefinitionInstanceRegistry;

    /** @var TypeRegistry */
    private $typeRegistry;

    private Inflector $inflector;

    public function setUp(): void
    {
        $this->inflector = (new InflectorFactory())->getInflector();
        $this->DefinitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->typeRegistry = new TypeRegistry(
            $this->DefinitionInstanceRegistry,
            new CustomTypes(),
            new CustomFieldRegistry(),
            new CustomFieldRegistry(),
            new CustomFieldRegistry(),
            new CustomFieldRegistry(),
            new InflectorFactory(),
        );
    }

    public function testGetQueryForBaseEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([BaseEntity::class]);

        $query = $this->typeRegistry->getQuery();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Query', $query->name);
        static::assertCount(2, $query->getFields());

        $expectedFields = [
            'id' => NonNull::class,
            'bool' => BooleanType::class,
            'date' => DateType::class,
            'int' => IntType::class,
            'float' => FloatType::class,
            'json' => JsonType::class,
            'string' => StringType::class
        ];

        $fieldName = $this->inflector->camelize((new \SwagGraphQL\Tests\_fixtures\BaseEntity)->getEntityName());
        $baseField = $query->getField($fieldName);
        static::assertObject($expectedFields, $baseField->getType());
        static::assertInputArgs([
            'id' => NonNull::class,
        ], $baseField);

        $pluralizedName = $this->inflector->pluralize($fieldName);
        $baseField = $query->getField($pluralizedName);
        static::assertConnectionObject($expectedFields, $baseField->getType());
        static::assertConnectionArgs($baseField);
    }

    public function testGetQueryForAssociationEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([AssociationEntity::class, ManyToManyEntity::class, ManyToOneEntity::class, MappingEntity::class]);

        $query = $this->typeRegistry->getQuery();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Query', $query->name);
        static::assertCount(6, $query->getFields());

        $expectedFields = [
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ];
        $fieldName = $this->inflector->camelize((new \SwagGraphQL\Tests\_fixtures\AssociationEntity)->getEntityName());
        $associationField = $query->getField($fieldName);
        static::assertObject($expectedFields, $associationField->getType());

        $pluralizedName = $this->inflector->pluralize($fieldName);
        $associationField = $query->getField($pluralizedName);
        static::assertConnectionObject($expectedFields, $associationField->getType());

        $expectedFields = [
            'association' => ObjectType::class,
        ];

        $fieldName = $this->inflector->camelize((new \SwagGraphQL\Tests\_fixtures\ManyToManyEntity)->getEntityName());
        $manyToManyField = $query->getField($fieldName);
        static::assertObject($expectedFields, $manyToManyField->getType());

        $pluralizedName = $this->inflector->pluralize($fieldName);
        $manyToManyField = $query->getField($pluralizedName);
        static::assertConnectionObject($expectedFields, $manyToManyField->getType());

        $expectedFields = [
            'association' => ObjectType::class,
        ];

        $fieldName = $this->inflector->camelize((new \SwagGraphQL\Tests\_fixtures\ManyToOneEntity)->getEntityName());
        $manyToOneField = $query->getField($fieldName);
        static::assertObject($expectedFields, $manyToOneField->getType());

        $pluralizedName = $this->inflector->pluralize($fieldName);
        $manyToOneField = $query->getField($pluralizedName);
        static::assertConnectionObject($expectedFields, $manyToOneField->getType());
    }

    public function testGetQueryIgnoresTranslationEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([ProductTranslationDefinition::class]);

        $query = $this->typeRegistry->getQuery();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Query', $query->name);
        static::assertCount(0, $query->getFields());
    }

    public function testGetQueryIgnoresMappingEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([ProductCategoryDefinition::class]);

        $query = $this->typeRegistry->getQuery();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Query', $query->name);
        static::assertCount(0, $query->getFields());
    }

    public function testGetMutationForBaseEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([BaseEntity::class]);

        $query = $this->typeRegistry->getMutation();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Mutation', $query->name);
        static::assertCount(3, $query->getFields());

        $create = new Mutation(Mutation::ACTION_CREATE, (new \SwagGraphQL\Tests\_fixtures\BaseEntity)->getEntityName());
        $createField = $query->getField($create->getName());
        static::assertObject([
            'id' => NonNull::class,
            'bool' => BooleanType::class,
            'date' => DateType::class,
            'int' => IntType::class,
            'float' => FloatType::class,
            'json' => JsonType::class,
            'string' => StringType::class
        ], $createField->getType());

        static::assertInputArgs([
            'id' => IDType::class,
            'bool' => BooleanType::class,
            'date' => DateType::class,
            'int' => IntType::class,
            'float' => FloatType::class,
            'json' => JsonType::class,
            'string' => StringType::class
        ], $createField);

        $update = new Mutation(Mutation::ACTION_UPDATE, (new \SwagGraphQL\Tests\_fixtures\BaseEntity)->getEntityName());
        $updateField = $query->getField($update->getName());
        static::assertObject([
            'id' => NonNull::class,
            'bool' => BooleanType::class,
            'date' => DateType::class,
            'int' => IntType::class,
            'float' => FloatType::class,
            'json' => JsonType::class,
            'string' => StringType::class
        ], $updateField->getType());

        static::assertInputArgs([
            'id' => NonNull::class,
            'bool' => BooleanType::class,
            'date' => DateType::class,
            'int' => IntType::class,
            'float' => FloatType::class,
            'json' => JsonType::class,
            'string' => StringType::class
        ], $updateField);

        $delete = new Mutation(Mutation::ACTION_DELETE, (new \SwagGraphQL\Tests\_fixtures\BaseEntity)->getEntityName());
        $deleteField = $query->getField($delete->getName());
        static::assertInstanceOf(IDType::class, $deleteField->getType());
        static::assertCount(1, $deleteField->args);
        static::assertInstanceOf(NonNull::class, $deleteField->getArg('id')->getType());
    }

    public function testGetMutationForAssociationEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([AssociationEntity::class, ManyToManyEntity::class, ManyToOneEntity::class, MappingEntity::class]);

        $query = $this->typeRegistry->getMutation();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Mutation', $query->name);
        static::assertCount(9, $query->getFields());

        $association = new Mutation(Mutation::ACTION_CREATE, (new \SwagGraphQL\Tests\_fixtures\AssociationEntity)->getEntityName());
        $associationField = $query->getField($association->getName());
        static::assertObject([
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ], $associationField->getType());
        static::assertConnectionObject([
            'association' => ObjectType::class,
        ], $associationField->getType()->getField('manyToMany')->getType());
        static::assertInputArgs([
            'id' => IDType::class,
            'manyToMany' => ListOfType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => InputObjectType::class
        ], $associationField);

        $association = new Mutation(Mutation::ACTION_UPDATE, (new \SwagGraphQL\Tests\_fixtures\AssociationEntity)->getEntityName());
        $associationField = $query->getField($association->getName());
        static::assertObject([
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ], $associationField->getType());
        static::assertConnectionObject([
            'association' => ObjectType::class,
        ], $associationField->getType()->getField('manyToMany')->getType());
        static::assertInputArgs([
            'id' => NonNull::class,
            'manyToMany' => ListOfType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => InputObjectType::class
        ], $associationField);

        $delete = new Mutation(Mutation::ACTION_DELETE, (new \SwagGraphQL\Tests\_fixtures\AssociationEntity)->getEntityName());
        $deleteField = $query->getField($delete->getName());
        static::assertInstanceOf(IDType::class, $deleteField->getType());
        static::assertCount(1, $deleteField->args);
        static::assertInstanceOf(NonNull::class, $deleteField->getArg('id')->getType());

        $manyToMany = new Mutation(Mutation::ACTION_CREATE, (new \SwagGraphQL\Tests\_fixtures\ManyToManyEntity)->getEntityName());
        $manyToManyField = $query->getField($manyToMany->getName());
        static::assertObject([
            'association' => ObjectType::class,
        ], $manyToManyField->getType());
        static::assertConnectionObject([
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ], $manyToManyField->getType()->getField('association')->getType());
        static::assertInputArgs([
            'id' => IDType::class,
            'association' => ListOfType::class,
        ], $manyToManyField);

        $manyToMany = new Mutation(Mutation::ACTION_UPDATE, (new \SwagGraphQL\Tests\_fixtures\ManyToManyEntity)->getEntityName());
        $manyToManyField = $query->getField($manyToMany->getName());
        static::assertObject([
            'association' => ObjectType::class,
        ], $manyToManyField->getType());
        static::assertConnectionObject([
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ], $manyToManyField->getType()->getField('association')->getType());
        static::assertInputArgs([
            'id' => NonNull::class,
            'association' => ListOfType::class,
        ], $manyToManyField);

        $delete = new Mutation(Mutation::ACTION_DELETE, (new \SwagGraphQL\Tests\_fixtures\ManyToManyEntity)->getEntityName());
        $deleteField = $query->getField($delete->getName());
        static::assertInstanceOf(IDType::class, $deleteField->getType());
        static::assertCount(1, $deleteField->args);
        static::assertInstanceOf(NonNull::class, $deleteField->getArg('id')->getType());

        $manyToOne = new Mutation(Mutation::ACTION_CREATE, (new \SwagGraphQL\Tests\_fixtures\ManyToOneEntity)->getEntityName());
        $manyToOneField = $query->getField($manyToOne->getName());
        static::assertObject([
            'association' => ObjectType::class,
        ], $manyToOneField->getType());
        static::assertConnectionObject([
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ], $manyToOneField->getType()->getField('association')->getType());
        static::assertInputArgs([
            'id' => IDType::class,
            'association' => ListOfType::class,
        ], $manyToOneField);

        $manyToOne = new Mutation(Mutation::ACTION_UPDATE, (new \SwagGraphQL\Tests\_fixtures\ManyToOneEntity)->getEntityName());
        $manyToOneField = $query->getField($manyToOne->getName());
        static::assertObject([
            'association' => ObjectType::class,
        ], $manyToOneField->getType());
        static::assertConnectionObject([
            'manyToMany' => ObjectType::class,
            'manyToOneId' => IDType::class,
            'manyToOne' => ObjectType::class
        ], $manyToOneField->getType()->getField('association')->getType());
        static::assertInputArgs([
            'id' => NonNull::class,
            'association' => ListOfType::class,
        ], $manyToOneField);

        $delete = new Mutation(Mutation::ACTION_DELETE, (new \SwagGraphQL\Tests\_fixtures\ManyToOneEntity)->getEntityName());
        $deleteField = $query->getField($delete->getName());
        static::assertInstanceOf(IDType::class, $deleteField->getType());
        static::assertCount(1, $deleteField->args);
        static::assertInstanceOf(NonNull::class, $deleteField->getArg('id')->getType());
    }

    public function testGetMutationIgnoresTranslationEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([ProductTranslationDefinition::class]);

        $query = $this->typeRegistry->getMutation();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Mutation', $query->name);
        static::assertCount(0, $query->getFields());
    }

    public function testGetMutationIgnoresMappingEntity()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([ProductCategoryDefinition::class]);

        $query = $this->typeRegistry->getMutation();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Mutation', $query->name);
        static::assertCount(0, $query->getFields());
    }

    public function testGetMutationWithDefault()
    {
        $this->DefinitionInstanceRegistry->expects($this->once())
            ->method('getDefinitions')
            ->willReturn([BaseEntityWithDefaults::class]);

        $query = $this->typeRegistry->getMutation();
        static::assertInstanceOf(ObjectType::class, $query);
        static::assertEquals('Mutation', $query->name);
        static::assertCount(3, $query->getFields());

        $create = new Mutation(Mutation::ACTION_CREATE, BaseEntityWithDefaults::getEntityName());
        $baseField = $query->getField($create->getName());
        static::assertObject([
            'id' => NonNull::class,
            'string' => StringType::class
        ], $baseField->getType());

        static::assertInputArgs([
            'id' => IDType::class,
            'string' => StringType::class
        ], $baseField);

        static::assertDefault(
            'test',
            $baseField->getArg('string')
        );

        $update = new Mutation(Mutation::ACTION_UPDATE, BaseEntityWithDefaults::getEntityName());
        $baseField = $query->getField($update->getName());
        static::assertObject([
            'id' => NonNull::class,
            'string' => StringType::class
        ], $baseField->getType());

        static::assertInputArgs([
            'id' => NonNull::class,
            'string' => StringType::class
        ], $baseField);

        static::assertFalse($baseField->getArg('string')->defaultValueExists());

        $delete = new Mutation(Mutation::ACTION_DELETE, BaseEntityWithDefaults::getEntityName());
        $deleteField = $query->getField($delete->getName());
        static::assertInstanceOf(IDType::class, $deleteField->getType());
        static::assertCount(1, $deleteField->args);
        static::assertInstanceOf(NonNull::class, $deleteField->getArg('id')->getType());
    }
}
