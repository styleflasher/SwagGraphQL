<?php declare(strict_types=1);

namespace SwagGraphQL\Actions;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Resolver\AssociationResolver;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class RenameMediaAction implements GraphQLField
{
    private const MEDIA_ID_ARGUMENT = 'mediaId';
    private const FILENAME_ARGUMENT = 'fileName';

    public function __construct(private readonly TypeRegistry $typeRegistry, private readonly FileSaver $fileSaver, private readonly EntityRepository $mediaRepository)
    {
    }

    public function returnType(): Type
    {
        return $this->typeRegistry->getObjectForDefinition(MediaDefinition::class);
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::MEDIA_ID_ARGUMENT, Type::nonNull(Type::id()))
            ->addField(self::FILENAME_ARGUMENT, Type::nonNull(Type::string()));
    }

    public function description(): string
    {
        return 'Renames the file with the given ID.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info)
    {
        $mediaId = $args[self::MEDIA_ID_ARGUMENT];
        $fileName = $args[self::FILENAME_ARGUMENT];

        $this->fileSaver->renameMedia($mediaId, $fileName, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $mediaId));
        AssociationResolver::addAssociations($criteria, $info->lookahead()->queryPlan(), new MediaDefinition());

        return $this->mediaRepository->search($criteria, $context)->get($mediaId);
    }
}
