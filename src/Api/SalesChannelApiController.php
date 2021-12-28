<?php declare(strict_types=1);

namespace SwagGraphQL\Api;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagGraphQL\Resolver\SalesChannelQueryResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class SalesChannelApiController extends AbstractController
{
    private Schema $schema;

    private SalesChannelQueryResolver $queryResolver;

    public function __construct(Schema $schema, SalesChannelQueryResolver $queryResolver)
    {
        $this->schema = $schema;
        $this->queryResolver = $queryResolver;
    }

    /**
     * @Route("api/storefront/graphql/generate-schema", name="storefront-api.graphql_generate_schema", methods={"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generateSchema(): Response
    {
        $fileName = sprintf('%s/../Resources/sales-channel-%s', __DIR__, ApiController::GRAPHQL_SCHEMA_FILE);

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
     * @Route("api/storefront/graphql", name="storefront-api.graphql", methods={"GET|POST"})
     *
     * @param Request $request
     * @param SalesChannelContext $context
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws UnsupportedContentTypeException
     */
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
            function ($rootValue, $args, $context, ResolveInfo $info) {
                return $this->queryResolver->resolve($rootValue, $args, $context, $info);
            }
        );

        return new JsonResponse($result->toArray());
    }
}
