<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class MappingEntity extends MappingEntityDefinition
{

    public function getEntityName(): string
    {
        return 'mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('association_id', 'associationId', AssociationEntity::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('many_to_many_id', 'manyToManyId', ManyToManyEntity::class))->setFlags(new PrimaryKey(), new Required()),

            new ManyToOneAssociationField('association', 'association_id', AssociationEntity::class),
            new ManyToOneAssociationField('manyToMany', 'many_to_many_id', ManyToManyEntity::class),
        ]);
    }
}
