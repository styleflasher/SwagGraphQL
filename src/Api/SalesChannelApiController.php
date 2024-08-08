<?php declare(strict_types=1);

namespace SwagGraphQL\Api;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\Resolver\SalesChannelQueryResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
class SalesChannelApiController extends AbstractController
{
    public const GRAPHQL_SCHEMA_FILE = 'schema.graphql';

    public function __construct(private readonly Schema $schema, private readonly SalesChannelQueryResolver $queryResolver)
    {
    }

    /**
     * @return Response
     */
    #[Route(path: '/store-api/graphql/generate-schema', name: 'store-api.graphql_generate_schema', methods: ['GET'])]
    public function generateSchema(): Response
    {
        $fileName = sprintf('%s/../Resources/sales-channel-%s', __DIR__, self::GRAPHQL_SCHEMA_FILE);

        file_put_contents($fileName, SchemaPrinter::doPrint($this->schema));
        return new Response();
    }

    /**
     * GraphQL Endpoint
     *
     * supports: @see https://graphql.github.io/learn/serving-over-http/#http-methods-headers-and-body
     * GET: query as query string
     * POST with JSON: query in body like {'query': '...'}
     * POST with application/graphql: query is complete body
     *
     *
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response
     * @throws UnsupportedContentTypeException
     */
    #[Route(path: '/store-api/graphql', name: 'store-api.graphql', methods: ['GET|POST'])]
    public function query(Request $request, SalesChannelContext $context): Response
    {
        //@TODO: refactor this
        $query = null;
        $variables = null;
        if ($request->getMethod() === Request::METHOD_POST) {
            if ($request->headers->get('content_type') === 'application/json') {
                /** @var string $content */
                $content = $request->getContent();
                $body = json_decode($content, true);
                $query = $body['query'];
                $variables = $body['variables'] ?? null;
            } else if ($request->headers->get('content_type') === 'application/graphql') {
                $query = $request->getContent();
            } else {
                /** @var string $contentType */
                $contentType = $request->headers->get('content_type');
                throw new UnsupportedContentTypeException(
                    $contentType,
                    'application/json',
                    'application/graphql'
                );
            }
        } else {
            $query = $request->query->get('query');
        }

        $result = GraphQL::executeQuery(
            $this->schema,
            $query,
            null,
            $context,
            $variables,
            null,
            // Default Resolver
            fn($rootValue, $args, $context, ResolveInfo $info) => $this->queryResolver->resolve($rootValue, $args, $context, $info)
        );

        return new JsonResponse($result->toArray());
    }
}
