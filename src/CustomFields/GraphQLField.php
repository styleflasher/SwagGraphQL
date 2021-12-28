<?php declare(strict_types=1);

namespace SwagGraphQL\CustomFields;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;

/**
 * @TODO: refacot this annotation :D
 * Mörder wäre es wenn wir hier das meiste über Annotations abfrühstücken könnten
 * sprich name über ne annotation an der Klasse
 * und Args über Annotations an properties die wir dann direkt setzen in dem resolve wrapper,
 * weswegen args gar nicht mehr an die resolve function übergeben werden müssten
 */
interface GraphQLField
{
    public function returnType(): Type;

    public function defineArgs(): FieldBuilderCollection;

    public function description(): string;

    public function resolve($rootValue, array $args, $context, ResolveInfo $resolveInfo);
}
