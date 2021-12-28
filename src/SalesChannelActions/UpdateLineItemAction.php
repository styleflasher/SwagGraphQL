<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Saleschannel\CartService;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\CustomTypes;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class UpdateLineItemAction implements GraphQLField
{
    private const KEY_ARGUMENT = 'key';
    private const QUANTITY_ARGUMENT = 'quantity';
    private const PAYLOAD_ARGUMENT = 'payload';
    private const STACKABLE_ARGUMENT = 'stackable';
    private const REMOVABLE_ARGUMENT = 'removable';
    private const PRIORITY_ARGUMENT = 'priority';
    private const LABEL_ARGUMENT = 'label';
    private const DESCRIPTION_ARGUMENT = 'description';
    private const COVER_ARGUMENT = 'coverId';

    private CartService $cartService;

    private TypeRegistry $typeRegistry;

    private CustomTypes $customTypes;

    private EntityRepositoryInterface $mediaRepository;

    public function __construct(CartService $cartService, TypeRegistry $typeRegistry, CustomTypes $customTypes, EntityRepositoryInterface $mediaRepository)
    {
        $this->cartService = $cartService;
        $this->typeRegistry = $typeRegistry;
        $this->customTypes = $customTypes;
        $this->mediaRepository = $mediaRepository;
    }

    public function returnType(): Type
    {
        return $this->customTypes->cart($this->typeRegistry);
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::KEY_ARGUMENT, Type::nonNull(Type::id()))
            ->addField(self::QUANTITY_ARGUMENT, Type::int())
            ->addField(self::PAYLOAD_ARGUMENT, $this->customTypes->json())
            ->addField(self::STACKABLE_ARGUMENT, Type::boolean())
            ->addField(self::REMOVABLE_ARGUMENT, Type::boolean())
            ->addField(self::PRIORITY_ARGUMENT, Type::int())
            ->addField(self::LABEL_ARGUMENT, Type::string())
            ->addField(self::DESCRIPTION_ARGUMENT, Type::string())
            ->addField(self::COVER_ARGUMENT, Type::id());
    }

    public function description(): string
    {
        return 'Update a LineItem from the Cart.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): Cart
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $id = $args[self::KEY_ARGUMENT];

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem = $this->cartService->getCart($context->getToken(), $context)->getLineItems()->get($id);
        $this->updateLineItem($lineItem, $args, $context->getContext());

        return $this->cartService->recalculate($cart, $context);
    }

    private function updateLineItem(LineItem $lineItem, array $args, Context $context): void
    {
        if (isset($args[self::QUANTITY_ARGUMENT])) {
            $lineItem->setQuantity($args[self::QUANTITY_ARGUMENT]);
        }

        if (isset($args[self::STACKABLE_ARGUMENT])) {
            $lineItem->setStackable($args[self::STACKABLE_ARGUMENT]);
        }

        if (isset($args[self::REMOVABLE_ARGUMENT])) {
            $lineItem->setRemovable($args[self::REMOVABLE_ARGUMENT]);
        }

//        if (isset($args[self::PRIORITY_ARGUMENT])) { //@TODO: check priority
//            $lineItem->setPriority($args[self::PRIORITY_ARGUMENT]);
//        }

        if (isset($args[self::LABEL_ARGUMENT])) {
            $lineItem->setLabel($args[self::LABEL_ARGUMENT]);
        }

        if (isset($args[self::DESCRIPTION_ARGUMENT])) {
            $lineItem->setDescription($args[self::DESCRIPTION_ARGUMENT]);
        }

        if (isset($args[self::COVER_ARGUMENT])) {
            $cover = $this->mediaRepository->search(new Criteria([$args[self::COVER_ARGUMENT]]), $context)->get($args[self::COVER_ARGUMENT]);

            if (!$cover) {
                throw new LineItemCoverNotFoundException($args[self::COVER_ARGUMENT], $lineItem->getKey());
            }

            $lineItem->setCover($cover);
        }
    }
}
