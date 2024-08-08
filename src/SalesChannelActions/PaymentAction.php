<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilder;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;

class PaymentAction implements GraphQLField
{
    private const ORDER_ID_ARGUMENT = 'orderId';
    private const FINISH_URL_ARGUMENT = 'finishUrl';

    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function returnType(): Type
    {
        return Type::string();
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addFieldBuilder(FieldBuilder::create(self::ORDER_ID_ARGUMENT, Type::nonNull(Type::id())))
            ->addFieldBuilder(FieldBuilder::create(self::FINISH_URL_ARGUMENT, Type::string()));
    }

    public function description(): string
    {
        return 'Pay the order.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): string
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $response = $this->paymentService->handlePaymentByOrder($args[self::ORDER_ID_ARGUMENT], $context, $args[self::FINISH_URL_ARGUMENT] ?? null);

        if (!$response) {
            throw new InvalidOrderException($args[self::ORDER_ID_ARGUMENT]);
        }

        return $response->getTargetUrl();
    }
}
