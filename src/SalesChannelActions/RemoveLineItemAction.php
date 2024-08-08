<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Saleschannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\CustomTypes;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class RemoveLineItemAction implements GraphQLField
{
    private const KEY_ARGUMENT = 'key';

    private readonly CartService $cartService;

    public function __construct(CartService $cartService, private readonly TypeRegistry $typeRegistry, private readonly CustomTypes $customTypes)
    {
        $this->cartService = $cartService;
    }

    public function returnType(): Type
    {
        return $this->customTypes->cart($this->typeRegistry);
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::KEY_ARGUMENT, Type::nonNull(Type::id()));
    }

    public function description(): string
    {
        return 'Remove a LineItem from the Cart.';
    }

    /**
     * @param SalesChannelContext $context
     */
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

        return $this->cartService->remove($cart, $id, $context);
    }
}
