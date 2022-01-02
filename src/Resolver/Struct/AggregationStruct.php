<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\Struct\Struct;

class AggregationStruct extends Struct
{
    protected string $name = '';

    /** @var AggregationBucketStruct[] */
    protected array $buckets = [];

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

        switch (get_class($aggregation)) {
            case AvgResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket(
                    [
                        'key' => ['avg' => $aggregation->getName()],
                        'results' => ['avg' => $aggregation->getAvg()]
                    ]
                );
                break;

            case CountResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket(
                    [
                        'key' => ['count' => $aggregation->getName()],
                        'results' => ['count' => $aggregation->getCount()]
                    ]
                );
                break;

            case EntityResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket(
                    [
                        'key' => ['entities' => $aggregation->getName()],
                        'results' => ['entities' => $aggregation->getEntities()]
                    ]
                );
                break;

            case MaxResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket(
                    [
                        'key' => ['max' => $aggregation->getName()],
                        'results' => ['max' => $aggregation->getMax()]
                    ]
                );
                break;

            case MinResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket(
                    [
                        'key' => ['min' => $aggregation->getName()],
                        'results' => ['min' => $aggregation->getMin()]
                    ]
                );
                break;

            case StatsResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket(
                    ['key' => [
                        'avg' => $aggregation->getName(),
                        'min' => $aggregation->getName(),
                        'max' => $aggregation->getName(),
                        'sum' => $aggregation->getName()
                    ],
                        'results' => [
                            'avg' => $aggregation->getAvg(),
                            'min' => $aggregation->getMin(),
                            'max' => $aggregation->getMax(),
                            'sum' => $aggregation->getSum()
                        ]
                    ]
                );
                break;

            case SumResult::class:
                $bucket = AggregationBucketStruct::fromAggregationBucket([
                    'key' => ['sum' => $aggregation->getName()],
                    'results' => ['sum' => $aggregation->getSum()]
                ]);
                break;

            default:
                $bucket = null;
        }

        $buckets[] = $bucket;

        return (new self())->assign([
            'name' => $aggregation->getName(),
            'buckets' => $buckets
        ]);
    }

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
}
