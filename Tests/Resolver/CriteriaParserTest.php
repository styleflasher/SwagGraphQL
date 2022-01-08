<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use SwagGraphQL\Resolver\CriteriaParser;

class CriteriaParserTest extends TestCase
{
    public function testParsePaginationForward(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'first' => 5,
            'after' => base64_encode('10')
        ],
            new ProductDefinition()
        );

        static::assertEquals(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
        static::assertEquals(5, $criteria->getLimit());
        static::assertEquals(10, $criteria->getOffset());
    }

    public function testParsePaginationBackward(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'last' => 5,
            'before' => base64_encode('15')
        ],
            new ProductDefinition()
        );

        static::assertEquals(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
        static::assertEquals(5, $criteria->getLimit());
        static::assertEquals(10, $criteria->getOffset());
    }

    public function testParseSorting(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'sortBy' => 'id',
            'sortDirection' => FieldSorting::DESCENDING
        ],
            new ProductDefinition()
        );

        static::assertEquals('id', $criteria->getSorting()[0]->getField());
        static::assertEquals(FieldSorting::DESCENDING, $criteria->getSorting()[0]->getDirection());
    }

    public function testParseEqualsQuery(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'query' => [
                'type' => 'equals',
                'field' => 'id',
                'value' => 'test'
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(EqualsFilter::class, $criteria->getFilters()[0]);
        static::assertEquals('product.id', $criteria->getFilters()[0]->getField());
        static::assertEquals('test', $criteria->getFilters()[0]->getValue());
    }

    public function testParseEqualsAnyQuery(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'query' => [
                'type' => 'equalsAny',
                'field' => 'id',
                'value' => 'test|fancy'
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(EqualsAnyFilter::class, $criteria->getFilters()[0]);
        static::assertEquals('product.id', $criteria->getFilters()[0]->getField());
        static::assertEquals('test', $criteria->getFilters()[0]->getValue()[0]);
        static::assertEquals('fancy', $criteria->getFilters()[0]->getValue()[1]);
    }

    public function testParseContainsQuery(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'query' => [
                'type' => 'contains',
                'field' => 'id',
                'value' => 'test'
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(ContainsFilter::class, $criteria->getFilters()[0]);
        static::assertEquals('product.id', $criteria->getFilters()[0]->getField());
        static::assertEquals('test', $criteria->getFilters()[0]->getValue());
    }

    public function testParseRangeQuery(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'query' => [
                'type' => 'range',
                'field' => 'id',
                'parameters' => [
                    [
                        'operator' => 'gt',
                        'value' => 5
                    ],
                    [
                        'operator' => 'lt',
                        'value' => 10
                    ],
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(RangeFilter::class, $criteria->getFilters()[0]);
        static::assertEquals('product.id', $criteria->getFilters()[0]->getField());
        static::assertEquals(5, $criteria->getFilters()[0]->getParameter('gt'));
        static::assertEquals(10, $criteria->getFilters()[0]->getParameter('lt'));
    }

    public function testParseNotQuery(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'query' => [
                'type' => 'not',
                'operator' => 'AND',
                'queries' => [
                    [
                        'type' => 'equals',
                        'field' => 'id',
                        'value' => 'test'
                    ]
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(NotFilter::class, $criteria->getFilters()[0]);
        static::assertEquals('AND', $criteria->getFilters()[0]->getOperator());

        $inner =  $criteria->getFilters()[0]->getQueries()[0];
        static::assertInstanceOf(EqualsFilter::class, $inner);
        static::assertEquals('product.id', $inner->getField());
        static::assertEquals('test', $inner->getValue());
    }

    public function testParseMultiQuery(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'query' => [
                'type' => 'multi',
                'operator' => 'AND',
                'queries' => [
                    [
                        'type' => 'equals',
                        'field' => 'id',
                        'value' => 'test'
                    ],
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => 'test|fancy'
                    ]
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(MultiFilter::class, $criteria->getFilters()[0]);
        static::assertEquals('AND', $criteria->getFilters()[0]->getOperator());

        $first =  $criteria->getFilters()[0]->getQueries()[0];
        static::assertInstanceOf(EqualsFilter::class, $first);
        static::assertEquals('product.id', $first->getField());
        static::assertEquals('test', $first->getValue());

        $second =  $criteria->getFilters()[0]->getQueries()[1];
        static::assertInstanceOf(EqualsAnyFilter::class, $second);
        static::assertEquals('product.id', $second->getField());
        static::assertEquals('test', $second->getValue()[0]);
        static::assertEquals('fancy', $second->getValue()[1]);
    }

    public function testParseMaxAggregation(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'aggregations' => [
                [
                    'type' => 'max',
                    'field' => 'id',
                    'name' => 'max_id'
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(MaxAggregation::class, $criteria->getAggregations()['max_id']);
        static::assertEquals('product.id', $criteria->getAggregations()['max_id']->getField());
        static::assertEquals('max_id', $criteria->getAggregations()['max_id']->getName());
    }

    public function testParseMinAggregation(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'aggregations' => [
                [
                    'type' => 'min',
                    'field' => 'id',
                    'name' => 'min_id'
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(MinAggregation::class, $criteria->getAggregations()['min_id']);
        static::assertEquals('product.id', $criteria->getAggregations()['min_id']->getField());
        static::assertEquals('min_id', $criteria->getAggregations()['min_id']->getName());
    }

    public function testParseAvgAggregation(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'aggregations' => [
                [
                    'type' => 'avg',
                    'field' => 'id',
                    'name' => 'avg_id'
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(AvgAggregation::class, $criteria->getAggregations()['avg_id']);
        static::assertEquals('product.id', $criteria->getAggregations()['avg_id']->getField());
        static::assertEquals('avg_id', $criteria->getAggregations()['avg_id']->getName());
    }

    public function testParseCountAggregation(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'aggregations' => [
                [
                    'type' => 'count',
                    'field' => 'id',
                    'name' => 'count_id'
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(CountAggregation::class, $criteria->getAggregations()['count_id']);
        static::assertEquals('product.id', $criteria->getAggregations()['count_id']->getField());
        static::assertEquals('count_id', $criteria->getAggregations()['count_id']->getName());
    }

    public function testParseStatsAggregation(): void
    {
        $criteria = CriteriaParser::buildCriteria([
            'aggregations' => [
                [
                    'type' => 'stats',
                    'field' => 'id',
                    'name' => 'stats_id'
                ]
            ]
        ],
            new ProductDefinition()
        );

        static::assertInstanceOf(StatsAggregation::class, $criteria->getAggregations()['stats_id']);
        static::assertEquals('product.id', $criteria->getAggregations()['stats_id']->getField());
        static::assertEquals('stats_id', $criteria->getAggregations()['stats_id']->getName());
    }
}
