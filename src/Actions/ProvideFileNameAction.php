<?php declare(strict_types=1);

namespace SwagGraphQL\Actions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Content\Media\File\FileNameProvider;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;

class ProvideFileNameAction implements GraphQLField
{
    private const FILE_NAME_ARGUMENT = 'fileName';
    private const FILE_EXTENSION_ARGUMENT = 'fileExtension';
    private const MEDIA_ID_ARGUMENT = 'mediaId';

    public function __construct(private readonly FileNameProvider $nameProvider)
    {
    }

    public function returnType(): Type
    {
        return Type::nonNull(Type::string());
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::FILE_NAME_ARGUMENT, Type::nonNull(Type::string()))
            ->addField(self::FILE_EXTENSION_ARGUMENT, Type::nonNull(Type::string()))
            ->addField(self::MEDIA_ID_ARGUMENT, Type::id());
    }

    public function description(): string
    {
        return 'Provides a unique filename based on the given one.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): string
    {
        $fileName = $args[self::FILE_NAME_ARGUMENT];
        $fileExtension = $args[self::FILE_EXTENSION_ARGUMENT];
        $mediaId = array_key_exists(self::MEDIA_ID_ARGUMENT, $args) ?
            $args[self::FILE_NAME_ARGUMENT] :
            null;

        return $this->nameProvider->provide($fileName, $fileExtension, $mediaId, $context);
    }
}
