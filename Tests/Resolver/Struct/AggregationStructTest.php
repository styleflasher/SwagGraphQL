<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\Resolver\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use SwagGraphQL\Resolver\Struct\AggregationStruct;

class AggregationStructTest extends TestCase
{
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $this->aggregator = $this->getContainer()->get(EntityAggregatorInterface::class);
        $this->definition = $this->getContainer()->get('Shopware\Core\Content\Product\ProductDefinition');

        $entity1 = new ProductEntity();
        $entity1->setId('1');
        $entity2 = new ProductEntity();
        $entity2->setId('2');
        $criteria = new Criteria;
        $criteria->setLimit(10);
        $criteria->setOffset(5);

        $this->criteria = $criteria;
    }

    public function testFromAggregationResultSimpleAggregation()
    {
        $criteria = clone $this->criteria;
        $criteria->addAggregation(new AvgAggregation('avg', 'name'));
        $context = Context::createDefaultContext();
        $aggregationResult = $this->aggregator->aggregate($this->definition, clone $criteria, $context);
        $aggregation = AggregationStruct::fromAggregationResult($aggregationResult);

        static::assertEquals('name', $aggregation->getName());
        static::assertCount(1, $aggregation->getBuckets());
        $bucket = $aggregation->getBuckets()[0];
        static::assertEquals([], $bucket->getKeys());
        static::assertCount(1, $bucket->getResults());
        $result = $bucket->getResults()[0];
        static::assertEquals('avg', $result->getType());
        static::assertEquals(14, $result->getResult());
    }

    public function testFromAggregationResultStatsAggregation()
    {
        $criteria = clone $this->criteria;
        $criteria->addAggregation(new StatsAggregation('stats', 'name'));
        $context = Context::createDefaultContext();
        $aggregationResult = $this->aggregator->aggregate($this->definition, clone $criteria, $context);
        $aggregation = AggregationStruct::fromAggregationResult($aggregationResult);

        static::assertEquals('name', $aggregation->getName());
        static::assertCount(1, $aggregation->getBuckets());
        $bucket = $aggregation->getBuckets()[0];
        static::assertEquals([], $bucket->getKeys());
        static::assertCount(5, $bucket->getResults());
        static::assertEquals('count', $bucket->getResults()[0]->getType());
        static::assertEquals(1, $bucket->getResults()[0]->getResult());
        static::assertEquals('avg', $bucket->getResults()[1]->getType());
        static::assertEquals(2.0, $bucket->getResults()[1]->getResult());
        static::assertEquals('sum', $bucket->getResults()[2]->getType());
        static::assertEquals(3.0, $bucket->getResults()[2]->getResult());
        static::assertEquals('min', $bucket->getResults()[3]->getType());
        static::assertEquals(4.0, $bucket->getResults()[3]->getResult());
        static::assertEquals('max', $bucket->getResults()[4]->getType());
        static::assertEquals(5.0, $bucket->getResults()[4]->getResult());
    }

    public function testFromAggregationResultCollection()
    {
        $criteria = clone $this->criteria;
        $criteria->addAggregation(new MaxAggregation('max', 'name'));
        $criteria->addAggregation(new AvgAggregation('avg', 'name'));
        $context = Context::createDefaultContext();
        $aggregationResult = $this->aggregator->aggregate($this->definition, clone $criteria, $context);
        $aggregations = AggregationStruct::fromCollection(new AggregationResultCollection([$aggregationResult]));

        static::assertCount(2, $aggregations);
        static::assertEquals('max', $aggregations[0]->getName());
        static::assertCount(1, $aggregations[0]->getBuckets());
        $bucket = $aggregations[0]->getBuckets()[0];
        static::assertEquals([], $bucket->getKeys());
        static::assertCount(1, $bucket->getResults());
        static::assertEquals('max', $bucket->getResults()[0]->getType());
        static::assertEquals(20, $bucket->getResults()[0]->getResult());

        static::assertEquals('avg', $aggregations[1]->getName());
        static::assertCount(1, $aggregations[1]->getBuckets());
        $bucket = $aggregations[1]->getBuckets()[0];
        static::assertEquals([], $bucket->getKeys());
        static::assertCount(1, $bucket->getResults());
        static::assertEquals('avg', $bucket->getResults()[0]->getType());
        static::assertEquals(14, $bucket->getResults()[0]->getResult());
    }

}
