<?php declare(strict_types=1);

namespace SwagGraphQL\Api;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use SwagGraphQL\Resolver\QueryResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ApiController extends AbstractController
{
    public const GRAPHQL_SCHEMA_FILE = 'schema.graphql';

    private Schema $schema;

    private QueryResolver $queryResolver;

    public function __construct(Schema $schema, QueryResolver $queryResolver)
    {
        $this->schema = $schema;
        $this->queryResolver = $queryResolver;
    }

    /**
     * @Route("api/graphql/generate-schema", name="graphql_generate_schema", methods={"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generateSchema(): Response
    {
        $fileName = sprintf('%s/../Resources/%s', __DIR__, self::GRAPHQL_SCHEMA_FILE);
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
     * @Route("api/graphql", name="graphql", methods={"GET|POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws UnsupportedContentTypeException
     */
    public function query(Request $request, Context $context): Response
    {
        //@TODO: refactor this
        $query = null;
        $variables = null;
        if ($request->getMethod() === Request::METHOD_POST) {
            $contentType = explode(';', $request->headers->get('content_type'))[0];
            if ($contentType === 'application/json') {
                /** @var string $content */
                $content = $request->getContent();
                $body = json_decode($content, true);
                $query = $body['query'];
                $variables = $body['variables'] ?? null;
            } else if ($contentType === 'application/graphql') {
                $query = $request->getContent();
            } else {
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
