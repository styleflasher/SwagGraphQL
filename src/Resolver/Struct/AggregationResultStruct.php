<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class AggregationResultStruct extends Struct
{
    protected string $type = '';

    protected $result;

    public function getType(): string
    {
        return $this->type;
    }

    public function getResult()
    {
        return $this->result;
    }
}
