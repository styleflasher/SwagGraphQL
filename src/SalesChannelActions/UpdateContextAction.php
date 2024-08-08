<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;

class UpdateContextAction implements GraphQLField
{
    private const SHIPPING_METHOD_ARGUMENT = 'shippingMethodId';
    private const PAYMENT_METHOD_ARGUMENT = 'paymentMethodId';
    private const SHIPPING_ADDRESS_ARGUMENT = 'shippingAddressId';
    private const BILLING_ADDRESS_ARGUMENT = 'billingAddressId';

    public function __construct(
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly EntityRepository             $paymentMethodRepository,
        private readonly EntityRepository             $shippingMethodRepository,
        private readonly EntityRepository             $addressRepository
    )
    {
    }

    public function returnType(): Type
    {
        return Type::nonNull(Type::id());
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::SHIPPING_METHOD_ARGUMENT, Type::id())
            ->addField(self::PAYMENT_METHOD_ARGUMENT, Type::id())
            ->addField(self::SHIPPING_ADDRESS_ARGUMENT, Type::id())
            ->addField(self::BILLING_ADDRESS_ARGUMENT, Type::id());
    }

    public function description(): string
    {
        return 'Update the context of the currently logged in Customer.';
    }

    /**
     * @param SalesChannelContext $context
     */
    public function resolve($rootValue, $args, $context, ResolveInfo $resolveInfo): string
    {
        $update = [];
        if (array_key_exists(self::SHIPPING_METHOD_ARGUMENT, $args)) {
            $update[self::SHIPPING_METHOD_ARGUMENT] = $this->validateShippingMethodId($args[self::SHIPPING_METHOD_ARGUMENT], $context);
        }
        if (array_key_exists(self::PAYMENT_METHOD_ARGUMENT, $args)) {
            $update[self::PAYMENT_METHOD_ARGUMENT] = $this->validatePaymentMethodId($args[self::PAYMENT_METHOD_ARGUMENT], $context);
        }
        if (array_key_exists(self::BILLING_ADDRESS_ARGUMENT, $args)) {
            $update[self::BILLING_ADDRESS_ARGUMENT] = $this->validateAddressId($args[self::BILLING_ADDRESS_ARGUMENT], $context);
        }
        if (array_key_exists(self::SHIPPING_ADDRESS_ARGUMENT, $args)) {
            $update[self::SHIPPING_ADDRESS_ARGUMENT] = $this->validateAddressId($args[self::SHIPPING_ADDRESS_ARGUMENT], $context);
        }

        $this->contextPersister->save($context->getToken(), $update, $context->getSalesChannelId());

        return $context->getToken();
    }

    private function validateShippingMethodId(string $shippingMethodId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shipping_method.id', $shippingMethodId));

        $valid = $this->shippingMethodRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($shippingMethodId, $valid->getIds(), true)) {
            throw ShippingException::shippingMethodNotFound($shippingMethodId);
        }

        return $shippingMethodId;
    }

    private function validatePaymentMethodId(string $paymentMethodId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payment_method.id', $paymentMethodId));

        $valid = $this->paymentMethodRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($paymentMethodId, $valid->getIds(), true)) {
            throw CustomerException::unknownPaymentMethod($paymentMethodId);
        }

        return $paymentMethodId;
    }

    private function validateAddressId(string $addressId, SalesChannelContext $context): string
    {
        if (!$context->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        $addresses = $this->addressRepository->search(new Criteria([$addressId]), $context->getContext());
        /** @var CustomerAddressEntity|null $address */
        $address = $addresses->get($addressId);

        if (!$address) {
            throw new AddressNotFoundException($addressId);
        }

        if ($address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundException($address->getCustomerId() . '/' . $context->getCustomer()->getId());
        }

        return $addressId;
    }
}
