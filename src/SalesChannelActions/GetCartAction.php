<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\CustomTypes;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class GetCartAction implements GraphQLField
{
    public const CART_NAME_ARGUMENT = 'name';

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
            ->addField(self::CART_NAME_ARGUMENT, Type::nonNull(Type::string()));
    }

    public function description(): string
    {
        return 'Get or Create an empty cart.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): Cart
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        return $this->cartService->getCart($context->getToken(), $context, $args[self::CART_NAME_ARGUMENT]);
    }
}
