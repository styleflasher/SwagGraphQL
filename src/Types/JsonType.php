<?php declare(strict_types=1);

namespace SwagGraphQL\Types;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Shopware\Core\Framework\Struct\Struct;

class JsonType extends ScalarType
{
    public string $name = 'Json';

    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     * @throws Error
     */
    public function serialize($value)
    {
        if ($value instanceof Struct) {
            return $value->jsonSerialize();
        }

        return $value;
    }

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * In the case of an invalid value this method must throw an Exception
     *
     * @param mixed $value
     * @return mixed
     * @throws Error
     */
    public function parseValue($value)
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true);
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * In the case of an invalid node or value this method must throw an Exception
     *
     * @param Node $valueNode
     * @param mixed[]|null $variables
     * @return mixed
     * @throws \Exception
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (!$valueNode instanceof StringValueNode) {
            $kind = 'undefined';
            if (property_exists($valueNode, 'kind')) {
                $kind = $valueNode->kind;
            }

            throw new Error('Query error: Can only parse strings got: ' . $kind, $valueNode);
        }

        return json_decode($valueNode->value, true);
    }
}
