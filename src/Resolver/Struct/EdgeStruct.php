<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Struct\Struct;

class EdgeStruct extends Struct
{
    protected ?Entity $node = null;

    protected string $cursor = '';

    public function getNode(): Entity
    {
        return $this->node;
    }

    public function getCursor(): string
    {
        return $this->cursor;
    }

    public static function fromElements(array $elements, int $offset): array
    {
        $edges = [];
        $index = 1;
        foreach ($elements as $element) {
            $edges[] = (new self())->assign([
                'node' => $element,
                'cursor' => base64_encode((string) ($offset + $index))
            ]);

            $index++;
        }

        return $edges;
    }
}
