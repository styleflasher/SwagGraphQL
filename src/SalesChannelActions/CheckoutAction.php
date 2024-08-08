<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Saleschannel\CartService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;
use SwagGraphQL\Schema\TypeRegistry;

class CheckoutAction implements GraphQLField
{
    private readonly CartService $cartService;

    public function __construct(CartService $cartService, private readonly TypeRegistry $typeRegistry, private readonly EntityRepository $orderRepository)
    {
        $this->cartService = $cartService;
    }

    public function returnType(): Type
    {
        return $this->typeRegistry->getObjectForDefinition(OrderDefinition::class);
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create();
    }

    public function description(): string
    {
        return 'Finish the order.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info)
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $orderId = $this->cartService->order($cart, $context);
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        $order = $this->orderRepository->search($criteria, $context->getContext())->get($orderId);

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }
}
