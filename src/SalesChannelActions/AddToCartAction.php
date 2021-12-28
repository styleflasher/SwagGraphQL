<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Saleschannel\CartService;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\CustomTypes;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class AddToCartAction implements GraphQLField
{
    private const PRODUCT_ID_ARGUMENT = 'productId';
    private const QUANTITY_ARGUMENT = 'quantity';
    private const PAYLOAD_ARGUMENT = 'payload';

    private CartService $cartService;

    private TypeRegistry $typeRegistry;

    private CustomTypes $customTypes;

    public function __construct(CartService $cartService, TypeRegistry $typeRegistry, CustomTypes $customTypes)
    {
        $this->cartService = $cartService;
        $this->typeRegistry = $typeRegistry;
        $this->customTypes = $customTypes;
    }

    public function returnType(): Type
    {
        return $this->customTypes->cart($this->typeRegistry);
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::PRODUCT_ID_ARGUMENT, Type::nonNull(Type::id()))
            ->addField(self::QUANTITY_ARGUMENT, Type::nonNull(Type::int()))
            ->addField(self::PAYLOAD_ARGUMENT, $this->customTypes->json());
    }

    public function description(): string
    {
        return 'Add a product to the Cart.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): Cart
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $id = $args[self::PRODUCT_ID_ARGUMENT];
        $payload = array_replace_recursive(['id' => $id], $args[self::PAYLOAD_ARGUMENT] ?? []);

        $lineItem = (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $args[self::QUANTITY_ARGUMENT]))
            ->setPayload($payload)
            ->setRemovable(true)
            ->setStackable(true);

        return $this->cartService->add($cart, $lineItem, $context);
    }
}
