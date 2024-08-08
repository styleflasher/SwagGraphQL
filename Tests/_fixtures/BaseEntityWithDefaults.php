<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class BaseEntityWithDefaults extends EntityDefinition
{

    public function getEntityName(): string
    {
        return 'base';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new StringField('string', 'string')
        ]);
    }

    public function getDefaults(EntityExistence $existence): array
    {
        if ($existence->exists()) {
            return [];
        }

        return [
            'string' => 'test'
        ];
    }
}
