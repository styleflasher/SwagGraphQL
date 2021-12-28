<?php declare(strict_types=1);

namespace SwagGraphQL\Schema;

use SwagGraphQL\CustomFields\GraphQLField;

class CustomFieldRegistry
{
    private array $fields = [];

    public function addField(string $name, GraphQLField $field): void
    {
        $this->fields[$name] = $field;
    }

    public function get(string $name): ?GraphQLField
    {
        return $this->fields[$name] ?? null;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
