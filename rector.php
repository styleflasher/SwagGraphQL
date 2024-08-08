<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Frosh\Rector\Set\ShopwareSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_81,
        SymfonySetList::SYMFONY_71,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        ShopwareSetList::SHOPWARE_6_5_0,
        ShopwareSetList::SHOPWARE_6_6_0,
    ])
    ->withConfiguredRule(
        RenameClassRector::class,
        [
            //'Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException' => LineItemCoverNotFoundException::class, // todo
            'Shopware\Core\Checkout\Cart\Storefront\CartService' => CartService::class,
            //'Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException' => UnknownPaymentMethodException
            'Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface' => EntityRepository::class,
        ]
    );