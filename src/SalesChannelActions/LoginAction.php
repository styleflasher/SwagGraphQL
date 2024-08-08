<?php declare(strict_types=1);

namespace SwagGraphQL\SalesChannelActions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use SwagGraphQL\CustomFields\GraphQLField;
use SwagGraphQL\Schema\SchemaBuilder\FieldBuilderCollection;

class LoginAction implements GraphQLField
{
    private const EMAIL_ARGUMENT = 'email';
    private const PASSWORD_ARGUMENT = 'password';

    public function __construct(private readonly AbstractLoginRoute $accountService)
    {
    }

    public function returnType(): Type
    {
        return Type::nonNull(Type::id());
    }

    public function defineArgs(): FieldBuilderCollection
    {
        return FieldBuilderCollection::create()
            ->addField(self::EMAIL_ARGUMENT, Type::nonNull(Type::string()))
            ->addField(self::PASSWORD_ARGUMENT, Type::nonNull(Type::string()));
    }

    public function description(): string
    {
        return 'Login with a email and password.';
    }

    public function resolve($rootValue, $args, $context, ResolveInfo $info): ContextTokenResponse
    {
        $email = $args[self::EMAIL_ARGUMENT];
        $password = $args[self::PASSWORD_ARGUMENT];
        $data = new RequestDataBag(['username' => $email, 'password' => $password]);

        return $this->accountService->login($data, $context);
    }
}
