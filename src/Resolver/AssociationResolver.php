<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class AssociationResolver
{
    private const TECHNICAL_FIELDS = [
        'edges',
        'node',
        'pageInfo',
        'aggregations',
        'results',
        'buckets',
        'keys'
    ];

    /**
     * adds all necessary Associations to the criteria
     * therefore it traverses the fieldSelection-array to get all Associations that should be loaded
     */
    public static function addAssociations(Criteria $criteria, array $queryPlan, EntityDefinition $definition): void
    {
        foreach ($queryPlan as $field => $selection) {
            if (count($selection['fields']) > 0) {
                if (static::isTechnicalField($field) && !$definition->getFields()->has($field)) {
                    static::addAssociations($criteria, $selection['fields'], $definition);
                    continue;
                }
                $association = static::getAssociationDefinition($definition, $field);
                $associationCriteria = CriteriaParser::buildCriteria($selection['args'], $association);
                static::addAssociations($associationCriteria, $selection['fields'], $association);

                $criteria->getAssociation($field) //@TODO: add association criteria
                    ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT) // exact total count will be returned (slow)
                    ->setLimit($associationCriteria->getLimit())
                    ->setOffset($associationCriteria->getOffset())
                    ->addFilter(...$associationCriteria->getFilters())
                    ->addPostFilter(...$associationCriteria->getPostFilters())
                    ->addSorting(...$associationCriteria->getSorting())
                    ->setTerm($associationCriteria->getTerm())
                    ->addAggregation(...$associationCriteria->getAggregations());
            }
        }
    }

    private static function isTechnicalField(string $field): bool
    {
        return in_array($field, static::TECHNICAL_FIELDS);
    }

    private static function getAssociationDefinition(EntityDefinition $definition,string $association): EntityDefinition
    {
        /** @var FieldCollection $fields */
        $fields = $definition->getFields();
        foreach ($fields as $field) {
            if ($field->getPropertyName() !== $association) {
                continue;
            }

            switch (true) {
                case $field instanceof ManyToManyAssociationField:
                    return $field->getReferenceDefinition();
                case $field instanceof OneToManyAssociationField:
                case $field instanceof ManyToOneAssociationField:
                case $field instanceof OneToOneAssociationField:
                return $field->getReferenceDefinition(); //@TODO: check return type (get entity instead of string) :getReferenceClass
            }
        }

        throw new \Exception(sprintf('Association "%s" on Entity "%s" not found', $association, $definition->getEntityName()));
    }
}
