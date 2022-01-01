<?php declare(strict_types=1);

namespace SwagGraphQL\Service;

use Defuse\Crypto\Core;
use ReflectionClass;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser as CoreAggregationParser;

class AggregationParser extends CoreAggregationParser
{
    public function buildAggregations(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if (!\is_array($payload['aggregations'])) {
            throw new InvalidAggregationQueryException('The aggregations parameter has to be a list of aggregations.');
        }

        //
        $reflectionAggregationParser = new ReflectionClass(CoreAggregationParser::class);
        $method = $reflectionAggregationParser->getMethod('parseAggregation');
        $method->setAccessible(true);

        foreach ($payload['aggregations'] as $index => $aggregation) {
            $parsed = $method->invoke(new CoreAggregationParser(), $index, $definition, $aggregation, $searchRequestException);

            if ($parsed) {
                $criteria->addAggregation($parsed);
            }
        }
    }
}
