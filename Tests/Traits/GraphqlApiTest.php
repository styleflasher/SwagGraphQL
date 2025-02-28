<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\Traits;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

trait GraphqlApiTest
{
    use IntegrationTestBehaviour;

    private function createGraphqlRequestRequest(string $query, string $method = Request::METHOD_POST): Request
    {
        $request = Request::create(
            'localhost',
            $method,
            [],
            [],
            [],
            [],
            json_encode(['query' => $query])
        );
        $request->headers->add(['content_type' => 'application/json']);

        return $request;
    }
}
