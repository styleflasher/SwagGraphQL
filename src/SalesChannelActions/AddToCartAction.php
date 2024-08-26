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
    private const LINE_ITEMS_ARGUMENT = 'items';

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
            ->addField(
                self::LINE_ITEMS_ARGUMENT,
                Type::nonNull(
                    Type::listOf(
                        $this->customTypes->cartItemInput()
                    )
                )
            );
    }

    public function description(): string
    {
        return 'Add several products to the Cart.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $resolveInfo): Cart
    {
        $lineItems = $args[self::LINE_ITEMS_ARGUMENT];

        $cart = $this->cartService->getCart($context->getToken(), $context);

        foreach($lineItems as $lineItemInput) {
            $lineItemType = $lineItemInput['lineItemType'] ?? LineItem::PRODUCT_LINE_ITEM_TYPE;
            $id = $lineItemInput['productId'];
            $payload = array_replace_recursive(['id' => $id], $lineItemInput['payload'] ?? []);

            $lineItem = $this->lineItemFactory->create(
                [
                    'type' => $lineItemType,
                    'id' => $id,
                    'quantity' => $lineItemInput['quantity'] ?? 1,
                    'payload' => $payload
                ],
                $context
            );

            $cart = $this->cartService->add($cart, $lineItem, $context);
        }

        return $cart;
    }
}
