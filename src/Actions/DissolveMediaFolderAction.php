<?php declare(strict_types=1);

namespace SwagGraphQL\Actions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Content\Media\MediaFolderService;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;

class DissolveMediaFolderAction implements GraphQLField
{
    private const FOLDER_ID_ARGUMENT = 'mediaFolderId';

    public function __construct(private readonly MediaFolderService $mediaFolderService)
    {
    }

    public function returnType(): Type
    {
        return Type::nonNull(Type::id());
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::FOLDER_ID_ARGUMENT, Type::nonNull(Type::id()));
    }

    public function description(): string
    {
        return 'Dissolves a media folder and puts the content one level higher.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): string
    {
        $folderId = $args[self::FOLDER_ID_ARGUMENT];
        $this->mediaFolderService->dissolve($folderId, $context);

        return $folderId;
    }
}
