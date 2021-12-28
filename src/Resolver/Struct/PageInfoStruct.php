<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;

class PageInfoStruct extends Struct
{
    protected string $endCursor = '';

    protected bool $hasNextPage = false;

    protected string $startCursor = '';

    protected bool $hasPreviousPage = false;

    public function getEndCursor(): ?string
    {
        return $this->endCursor;
    }

    public function getHasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    public function getStartCursor(): ?string
    {
        return $this->startCursor;
    }

    public function getHasPreviousPage(): bool
    {
        return $this->hasPreviousPage;
    }

    public static function fromCriteria(Criteria $criteria, int $total): PageInfoStruct
    {
        $limit = $criteria->getLimit() ?? $total;
        $offset = $criteria->getOffset() ?? 0;

        return (new self())->assign([
            'endCursor' => $total === 0 ? null : base64_encode((string) ($limit + $offset)),
            'hasNextPage' => $total >= $limit + $offset,
            'startCursor' => $total === 0 ? null :base64_encode((string) ($offset + 1)),
            'hasPreviousPage' => $offset > 0
        ]);
    }
}
