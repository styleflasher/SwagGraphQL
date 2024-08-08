<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\CustomTypes;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class AddToCartAction implements GraphQLField
{
    private const PRODUCT_ID_ARGUMENT = 'productId';
    private const QUANTITY_ARGUMENT = 'quantity';
    private const PAYLOAD_ARGUMENT = 'payload';

    private const LINE_ITEM_TYPE_ARGUMENT = 'lineItemType';

    public function __construct(
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly CartService             $cartService,
        private readonly TypeRegistry            $typeRegistry,
        private readonly CustomTypes             $customTypes)
    {
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
            ->addField(self::PAYLOAD_ARGUMENT, $this->customTypes->json())
            ->addField(self::LINE_ITEM_TYPE_ARGUMENT, Type::string());
    }

    public function description(): string
    {
        return 'Add a product to the Cart.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): Cart
    {
        $lineItemType = $args[self::LINE_ITEM_TYPE_ARGUMENT] ?? LineItem::PRODUCT_LINE_ITEM_TYPE;
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $id = $args[self::PRODUCT_ID_ARGUMENT];
        $payload = array_replace_recursive(['id' => $id], $args[self::PAYLOAD_ARGUMENT] ?? []);

        $lineItem = $this->lineItemFactory->create(
            [
                'type' => $lineItemType,
                'id' => $id,
                'quantity' => $args[self::QUANTITY_ARGUMENT] ?? 1,
                'payload' => $payload
            ],
            $context
        );

        return $this->cartService->add($cart, $lineItem, $context);
    }
}
