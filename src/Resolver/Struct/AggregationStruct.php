<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Struct\Struct;

class AggregationStruct extends Struct
{
    protected string $name = '';

    /** @var AggregationBucketStruct[] */
    protected array $buckets = [];

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return AggregationBucketStruct[]
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    /**
     * @return AggregationStruct[]
     */
    public static function fromCollection(AggregationResultCollection $collection): array
    {
        $aggregations = [];
        foreach ($collection->getElements() as $result) {
            $aggregations[] = static::fromAggregationResult($result);
        }

        return $aggregations;
    }

    public static function fromAggregationResult(AggregationResult $aggregation): AggregationStruct
    {
        $buckets = [];
        //@TODO: check if aggregation->getExtension is the right property accessor
        foreach ($aggregation->getExtensions() as $result) {
            $buckets[] = AggregationBucketStruct::fromAggregationBucket($result);
        }

        return (new self())->assign([
            'name' => $aggregation->getName(),
            'buckets' => $buckets
        ]);
    }
}
