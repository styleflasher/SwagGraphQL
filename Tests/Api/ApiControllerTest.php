<?php declare(strict_types=1);

namespace SwagGraphQL\Tests\Api;

use GraphQL\Type\Introspection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagGraphQL\Api\ApiController;
use SwagGraphQL\Api\UnsupportedContentTypeException;
use SwagGraphQL\Factory\InflectorFactory;
use SwagGraphQL\Resolver\QueryResolver;
use SwagGraphQL\Schema\SchemaFactory;
use SwagGraphQL\Schema\TypeRegistry;
use SwagGraphQL\Tests\Traits\GraphqlApiTest;
use Symfony\Component\HttpFoundation\Request;

class ApiControllerTest extends TestCase
{
    use GraphqlApiTest;

    /** @var ApiController */
    private $apiController;

    /** @var Context */
    private $context;

    /** @var EntityRepository */
    private $repository;

    public function setUp(): void
    {
        $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $schema = SchemaFactory::createSchema($this->getContainer()->get(TypeRegistry::class));
        $inflector = new InflectorFactory();

        $this->apiController = new ApiController($schema, new QueryResolver($this->getContainer(), $registry, $inflector));
        $this->context = Context::createDefaultContext();
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testGenerateSchema()
    {
        @unlink(__DIR__ . '/../../src/Resources/schema.graphql');

        $response = $this->apiController->generateSchema();
        static::assertEquals(200, $response->getStatusCode());
        static::assertFileExists(__DIR__ . '/../../src/Resources/schema.graphql');
    }

    public function testQueryIntrospectionQuery()
    {
        $request = $this->createGraphqlRequestRequest(Introspection::getIntrospectionQuery());
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $data);
        static::assertArrayNotHasKey('errors', $data);
    }

    public function testQueryProductWithoutData()
    {
        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertNotEmpty($data['data']['products']['edges']);
        static::assertNotEquals(0, $data['data']['products']['total']);
    }

    public function testQueryGET()
    {
        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ';
        $request = Request::create(
            'localhost',
            Request::METHOD_GET,
            ['query' => $query]
        );
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertNotEmpty($data['data']['products']['edges']);
        static::assertNotEquals(0, $data['data']['products']['total']);
    }

    public function testQueryWithApplicationGraphQL()
    {
        $query = '
            {
	            products {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ';

        $request = Request::create(
            'localhost',
            Request::METHOD_POST,
            [],
            [],
            [],
            [],
            $query
        );

        $request->headers->add(['content_type' => 'application/graphql']);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        static::assertArrayNotHasKey('errors', $data);
        static::assertNotEmpty($data['data']['products']['edges']);
        static::assertNotEquals(0, $data['data']['products']['total']);
    }

    public function testWithUnsupportedContentType()
    {
        $this->expectException(UnsupportedContentTypeException::class);
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('content_type', 'application/svg');
        $this->apiController->query($request, $this->context);
    }

    public function testQueryProductWithOneProduct()
    {
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = "
            query {
	            product(id: \"$productId\") {
	                id
		            name
	            }
            }
        ";
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);

        $productResult = $data['data']['product'];
        static::assertCount(2, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
    }

    public function testQueryProductsWithOneProduct()
    {
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(1, $data['data']['products']['edges']);

        $productResult = $data['data']['products']['edges'][0]['node'];
        static::assertCount(2, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
        static::assertEquals(1, $data['data']['products']['total']);
    }

    public function testQueryProductsWithMultipleProduct()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstProductId = $ids[0];
        $secondProductId = $ids[1];
        $thirdProductId = $ids[2];
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $firstProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'first product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
            [
                'id' => $secondProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'second product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $thirdProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'third product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(3, $data['data']['products']['edges']);

        $firstProduct = $data['data']['products']['edges'][0]['node'];
        static::assertCount(2, $firstProduct);
        static::assertEquals('first product', $firstProduct['name']);
        static::assertEquals($firstProductId, $firstProduct['id']);

        $secondProduct = $data['data']['products']['edges'][1]['node'];
        static::assertCount(2, $secondProduct);
        static::assertEquals('second product', $secondProduct['name']);
        static::assertEquals($secondProductId, $secondProduct['id']);

        $thirdProduct = $data['data']['products']['edges'][2]['node'];
        static::assertCount(2, $thirdProduct);
        static::assertEquals('third product', $thirdProduct['name']);
        static::assertEquals($thirdProductId, $thirdProduct['id']);
        static::assertEquals(3, $data['data']['products']['total']);
    }

    public function testQueryProductsWithFilter()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstProductId = $ids[0];
        $secondProductId = $ids[1];
        $thirdProductId = $ids[2];
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $firstProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'first product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
            [
                'id' => $secondProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'second product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $thirdProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'third product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = "
            query {
	            products (
	                query:  {
	                    type: equals
	                    field: \"id\"
	                    value: \"{$firstProductId}\"
	                }
	            ) {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ";
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(1, $data['data']['products']['edges']);

        $firstProduct = $data['data']['products']['edges'][0]['node'];
        static::assertCount(2, $firstProduct);
        static::assertEquals('first product', $firstProduct['name']);
        static::assertEquals($firstProductId, $firstProduct['id']);

        static::assertEquals(1, $data['data']['products']['total']);
    }

    public function testQueryProductsWithNestedFilter()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstProductId = $ids[0];
        $secondProductId = $ids[1];
        $thirdProductId = $ids[2];
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $firstProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'first product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
            [
                'id' => $secondProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test 2'],
                'name' => 'second product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $thirdProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'third product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products (
	                sortBy: "id"
	                query: {
	                    type: equals
	                    field: "manufacturer.name"
	                    value: "test"
	                }
	            ) {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(2, $data['data']['products']['edges']);

        $firstProduct = $data['data']['products']['edges'][0]['node'];
        static::assertCount(2, $firstProduct);
        static::assertEquals('first product', $firstProduct['name']);
        static::assertEquals($firstProductId, $firstProduct['id']);

        $secondProduct = $data['data']['products']['edges'][1]['node'];
        static::assertCount(2, $secondProduct);
        static::assertEquals('third product', $secondProduct['name']);
        static::assertEquals($thirdProductId, $secondProduct['id']);

        static::assertEquals(2, $data['data']['products']['total']);
    }

    public function testQueryProductsWithPagination()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstProductId = $ids[0];
        $secondProductId = $ids[1];
        $thirdProductId = $ids[2];
        $fourthProductId = $ids[3];
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $firstProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'z product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
            [
                'id' => $secondProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'b product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $thirdProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'c product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $fourthProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'a product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products (
	                sortBy: "name"
	                sortDirection: DESC
	                first: 2
	                after: "MQ=="
	            ) {
	                edges {
	                    node {
	                        id
		                    name
	                    }
	                }
	                total
	                pageInfo {
	                    hasNextPage
	                    endCursor
	                }
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(2, $data['data']['products']['edges']);

        $firstProduct = $data['data']['products']['edges'][0]['node'];
        static::assertCount(2, $firstProduct);
        static::assertEquals('c product', $firstProduct['name']);
        static::assertEquals($thirdProductId, $firstProduct['id']);

        $secondProduct = $data['data']['products']['edges'][1]['node'];
        static::assertCount(2, $secondProduct);
        static::assertEquals('b product', $secondProduct['name']);
        static::assertEquals($secondProductId, $secondProduct['id']);

        static::assertEquals(4, $data['data']['products']['total']);

        static::assertEquals(true, $data['data']['products']['pageInfo']['hasNextPage']);
        static::assertEquals('Mw==', $data['data']['products']['pageInfo']['endCursor']);
    }

    public function testQueryProductsWithAggregation()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstProductId = $ids[0];
        $secondProductId = $ids[1];
        $thirdProductId = $ids[2];
        $fourthProductId = $ids[3];
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $firstProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'z product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
            [
                'id' => $secondProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'b product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $thirdProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'c product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $fourthProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'a product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products (
	                aggregations: [
	                    {
	                        type: sum
	                        field: "tax.taxRate"
	                        name: "tax_sum"
	                    },
	                    {
	                        type: avg
	                        field: "tax.taxRate"
	                        name: "tax_avg"
	                    }
	                ]
	            ) {
	                total
	                aggregations {
	                    name
	                    buckets {
	                        keys {
	                            field
	                            value
	                        }
	                        results {
	                            type
	                            result
	                        }
	                    }
	                }
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data, print_r($data, true));

        static::assertEquals(4, $data['data']['products']['total']);

        static::assertCount(2, $data['data']['products']['aggregations']);

        $firstAggregation = $data['data']['products']['aggregations'][0];
        static::assertEquals('tax_sum', $firstAggregation['name']);
        static::assertCount(1, $firstAggregation['buckets']);
        $bucket = $firstAggregation['buckets'][0];
        static::assertCount(0, $bucket['keys']);
        static::assertEquals('sum', $bucket['results'][0]['type']);
        static::assertEquals(52, $bucket['results'][0]['result']);

        $secondAggregation = $data['data']['products']['aggregations'][1];
        static::assertEquals('tax_avg', $secondAggregation['name']);
        static::assertCount(1, $secondAggregation['buckets']);
        $bucket = $secondAggregation['buckets'][0];
        static::assertCount(0, $bucket['keys']);
        static::assertEquals('avg', $bucket['results'][0]['type']);
        static::assertEquals(13, $bucket['results'][0]['result']);
    }

    public function testQueryProductsWithGroupByAggregation()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstProductId = $ids[0];
        $secondProductId = $ids[1];
        $thirdProductId = $ids[2];
        $fourthProductId = $ids[3];
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $firstProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'manufacturer1'],
                'name' => 'z product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
            [
                'id' => $secondProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'manufacturer2'],
                'name' => 'b product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $thirdProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'manufacturer1'],
                'name' => 'c product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
            [
                'id' => $fourthProductId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'manufacturer2'],
                'name' => 'a product',
                'tax' => ['id' => $taxId],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products (
	                aggregations: [
	                    {
	                        type: sum
	                        field: "tax.taxRate"
	                        name: "tax_sum"
	                        groupByFields: ["product.manufacturer.name"]
	                    }
	                ]
	            ) {
	                total
	                aggregations {
	                    name
	                    buckets {
	                        keys {
	                            field
	                            value
	                        }
	                        results {
	                            type
	                            result
	                        }
	                    }
	                }
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data, print_r($data, true));

        static::assertEquals(4, $data['data']['products']['total']);

        static::assertCount(1, $data['data']['products']['aggregations']);

        $firstAggregation = $data['data']['products']['aggregations'][0];
        static::assertEquals('tax_sum', $firstAggregation['name']);
        static::assertCount(2, $firstAggregation['buckets']);
        $firstBucket = $firstAggregation['buckets'][0];
        static::assertEquals('product.manufacturer.name', $firstBucket['keys'][0]['field']);
        static::assertEquals('manufacturer1', $firstBucket['keys'][0]['value']);
        static::assertEquals('sum', $firstBucket['results'][0]['type']);
        static::assertEquals(26, $firstBucket['results'][0]['result']);

        $secondBucket = $firstAggregation['buckets'][1];
        static::assertEquals('product.manufacturer.name', $secondBucket['keys'][0]['field']);
        static::assertEquals('manufacturer2', $secondBucket['keys'][0]['value']);
        static::assertEquals('sum', $secondBucket['results'][0]['type']);
        static::assertEquals(26, $secondBucket['results'][0]['result']);
    }

    public function testQueryProductsIncludesManyToOne()
    {
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
		                    manufacturer {
		                        name
		                    }
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(1, $data['data']['products']['edges']);

        $productResult = $data['data']['products']['edges'][0]['node'];
        static::assertCount(3, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
        static::assertCount(1, $productResult['manufacturer']);
        static::assertEquals('test', $productResult['manufacturer']['name']);

        static::assertEquals(1, $data['data']['products']['total']);
    }

    public function testQueryProductsIncludesOneToMany()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstId = $ids[0];
        $secondId = $ids[1];
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
                'prices' => [
                    [
                        'id' => $firstId,
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'rule' => [
                            'id' => $ruleId,
                            'name' => 'test',
                            'priority' => 1,
                        ],
                        'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10],
                    ],
                    [
                        'id' => $secondId,
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 5,
                        'rule' => [
                            'id' => $ruleId,
                            'name' => 'test',
                            'priority' => 1,
                        ],
                        'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5],
                    ],
                ],
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
		                    prices {
		                        edges {
		                            node {
		                            	quantityStart
		                            }
		                        }
		                    }
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(1, $data['data']['products']['edges']);

        $productResult = $data['data']['products']['edges'][0]['node'];
        static::assertCount(3, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
        static::assertCount(2, $productResult['prices']['edges']);

        $firstPriceRule = $productResult['prices']['edges'][0]['node'];
        static::assertCount(1, $firstPriceRule);
        static::assertEquals(1, $firstPriceRule['quantityStart']);

        $secondPriceRule = $productResult['prices']['edges'][1]['node'];
        static::assertCount(1, $secondPriceRule);
        static::assertEquals(5, $secondPriceRule['quantityStart']);

        static::assertEquals(1, $data['data']['products']['total']);
    }

    public function testQueryProductsWithFilteredOneToMany()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstId = $ids[0];
        $secondId = $ids[1];
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
                'prices' => [
                    [
                        'id' => $firstId,
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'rule' => [
                            'id' => $ruleId,
                            'name' => 'test',
                            'priority' => 1,
                        ],
                        'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10],
                    ],
                    [
                        'id' => $secondId,
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 5,
                        'rule' => [
                            'id' => $ruleId,
                            'name' => 'test',
                            'priority' => 1,
                        ],
                        'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5],
                    ],
                ],
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
		                    prices(
		                        query: {
	                                type: equals
	                                field: "quantityStart"
	                                value: "5"
	                            }
	                        ) {
		                        edges {
		                            node {
		                            	quantityStart
		                            }
		                        }
		                    }
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data, print_r($data, true));
        static::assertCount(1, $data['data']['products']['edges']);

        $productResult = $data['data']['products']['edges'][0]['node'];
        static::assertCount(3, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
        static::assertCount(1, $productResult['prices']['edges']);

        $priceRule = $productResult['prices']['edges'][0]['node'];
        static::assertCount(1, $priceRule);
        static::assertEquals(5, $priceRule['quantityStart']);

        static::assertEquals(1, $data['data']['products']['total']);
    }

    public function testQueryProductsIncludesOneToManyNested()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstId = $ids[0];
        $secondId = $ids[1];
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $firstRuleId = Uuid::randomHex();
        $secondRuleId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
                'prices' => [
                    [
                        'id' => $firstId,
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'rule' => [
                            'id' => $firstRuleId,
                            'name' => 'first Rule',
                            'priority' => 1,
                        ],
                        'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10],
                    ],
                    [
                        'id' => $secondId,
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 5,
                        'rule' => [
                            'id' => $secondRuleId,
                            'name' => 'second Rule',
                            'priority' => 1,
                        ],
                        'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5],
                    ],
                ],
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
		                    prices {
		                        edges {
		                            node {
		                            	quantityStart
		                            	rule {
		                            	    name
		                            	}
		                            }
		                        }
		                    }
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(1, $data['data']['products']['edges']);

        $productResult = $data['data']['products']['edges'][0]['node'];
        static::assertCount(3, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
        static::assertCount(2, $productResult['prices']['edges']);

        $firstPriceRule = $productResult['prices']['edges'][0]['node'];
        static::assertCount(2, $firstPriceRule);
        static::assertEquals(1, $firstPriceRule['quantityStart']);
        static::assertCount(1, $firstPriceRule['rule']);
        static::assertEquals('first Rule', $firstPriceRule['rule']['name']);

        $secondPriceRule = $productResult['prices']['edges'][1]['node'];
        static::assertCount(2, $secondPriceRule);
        static::assertEquals(5, $secondPriceRule['quantityStart']);
        static::assertCount(1, $secondPriceRule['rule']);
        static::assertEquals('second Rule', $secondPriceRule['rule']['name']);

        static::assertEquals(1, $data['data']['products']['total']);
    }

    public function testQueryProductsIncludesManyToManyOnce()
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        sort($ids);
        $firstId = $ids[0];
        $secondId = $ids[1];
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['id' => $taxId, 'taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
                'categories' => [
                    [
                        'id' => $firstId,
                        'name' => 'first Category'
                    ],
                    [
                        'id' => $secondId,
                        'name' => 'second Category'
                    ],
                ]
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = '
            query {
	            products {
	                edges {
	                    node {
	                        id
		                    name
		                    categories {
		                        edges {
		                            node {
		                                name
		                            }
		                        }
		                    }
	                    }
	                }
	                total
	            }
            }
        ';
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(1, $data['data']['products']['edges']);

        $productResult = $data['data']['products']['edges'][0]['node'];
        static::assertCount(3, $productResult);
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($productId, $productResult['id']);
        static::assertCount(2, $productResult['categories']['edges']);

        $firstCategory = $productResult['categories']['edges'][0]['node'];
        static::assertCount(1, $firstCategory);
        static::assertEquals('first Category', $firstCategory['name']);

        $secondCategory = $productResult['categories']['edges'][1]['node'];
        static::assertCount(1, $secondCategory);
        static::assertEquals('second Category', $secondCategory['name']);
    }

    public function testCreateProduct()
    {
        $manufacturerId = Uuid::randomHex();
        $query = "
            mutation {
	            createProduct(
	                name: \"product\"
	                manufacturer: {
	                    id: \"{$manufacturerId}\"
	                    name: \"test\"
	                }
	                tax: {taxRate: 13, name: \"tax\"}
	                price: \"{\\\"gross\\\": 10, \\\"net\\\": 9}\"
	                stock: 25
	            ) {
		            id
		            name
		            manufacturer {
		                id
		            }
	            }
            }
        ";
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(3, $data['data']['createProduct']);

        $productResult = $data['data']['createProduct'];
        static::assertEquals('product', $productResult['name']);
        static::assertEquals($manufacturerId, $productResult['manufacturer']['id']);
    }

    public function testUpdateProduct()
    {
        $productId = Uuid::randomHex();
        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
        ];
        $this->repository->create($products, $this->context);

        $query = "
            mutation {
	            updateProduct(
	                id: \"{$productId}\"
	                name: \"new product\"
	            ) {
		            id
		            name
	            }
            }
        ";
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        static::assertArrayNotHasKey('errors', $data);
        static::assertCount(2, $data['data']['updateProduct']);

        $productResult = $data['data']['updateProduct'];
        static::assertEquals('new product', $productResult['name']);
    }

    public function testMutationDeleteProduct()
    {
        $productId = Uuid::randomHex();

        $products = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'product',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
                'stock' => 25,
            ],
        ];

        $this->repository->create($products, $this->context);

        $query = "
            mutation {
	            deleteProduct(
	                id: \"{$productId}\"
	            )
            }
        ";
        $request = $this->createGraphqlRequestRequest($query);
        $response = $this->apiController->query($request, $this->context);
        static::assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        static::assertArrayNotHasKey('errors', $data);
        static::assertEquals($productId, $data['data']['deleteProduct']);

        static::assertCount(0, $this->repository->search(new Criteria([$productId]), $this->context)->getIds());
    }
}
