<?php

namespace DevTheorem\Phaster;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Teapot\{HttpException, StatusCode};

/**
 * @api
 */
class RouteHandler
{
    private EntitiesFactory $entitiesFactory;

    public function __construct(EntitiesFactory $factory)
    {
        $this->entitiesFactory = $factory;
    }

    /**
     * @param class-string<Entities> $class
     */
    public function search(string $class, int $defaultLimit = 25, int $maxLimit = 1000): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response) use ($class, $factory, $defaultLimit, $maxLimit): ResponseInterface {
            $params = [
                'q' => [],
                'sort' => [],
                'fields' => [],
                'offset' => 0,
                'limit' => $defaultLimit,
            ];

            /**
             * @var mixed[]|string $value
             */
            foreach ($request->getQueryParams() as $param => $value) {
                if (!array_key_exists($param, $params)) {
                    throw new HttpException("Unrecognized parameter '{$param}'", StatusCode::BAD_REQUEST);
                }

                if ($param === 'q' || $param === 'sort') {
                    if (!is_array($value)) {
                        throw new HttpException("Parameter '{$param}' must be an array", StatusCode::BAD_REQUEST);
                    }

                    $params[$param] = $value;
                } elseif ($param === 'fields') {
                    if (!is_string($value)) {
                        throw new HttpException('fields parameter must be a string');
                    }

                    $params[$param] = explode(',', $value);
                } elseif (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new HttpException("Parameter '{$param}' must be an integer", StatusCode::BAD_REQUEST);
                } else {
                    $params[$param] = (int) $value;
                }
            }

            if ($params['limit'] === 0) {
                if ($params['offset'] !== 0) {
                    throw new HttpException('Limit must be greater than zero', StatusCode::BAD_REQUEST);
                } elseif ($defaultLimit !== 0) {
                    throw new HttpException('Limit must be greater than zero', StatusCode::FORBIDDEN);
                }
            } elseif ($params['limit'] > $maxLimit) {
                throw new HttpException("Limit cannot exceed {$maxLimit}", StatusCode::FORBIDDEN);
            }

            $checkLimit = $params['limit'];

            if ($checkLimit !== 0) {
                $checkLimit++; // request 1 extra item to determine if on the last page
            }

            $instance = $factory->createEntities($class);
            $entities = $instance->getEntities($params['q'], $params['fields'], $params['sort'], $params['offset'], $checkLimit);

            if ($checkLimit !== 0) {
                $resultCount = count($entities);
                $lastPage = ($resultCount < $checkLimit);

                if (!$lastPage) {
                    array_pop($entities); // remove extra item
                }

                $output = [
                    'offset' => $params['offset'],
                    'limit' => $params['limit'],
                    'lastPage' => $lastPage,
                    'data' => $entities,
                ];
            } else {
                $output = ['data' => $entities];
            }

            $response->getBody()->write(json_encode($output, JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    /**
     * @param class-string<Entities> $class
     */
    public function count(string $class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response) use ($class, $factory): ResponseInterface {
            $query = [];

            foreach ($request->getQueryParams() as $param => $value) {
                if ($param !== 'q') {
                    throw new HttpException("Unrecognized parameter '{$param}'", StatusCode::BAD_REQUEST);
                }
                if (!is_array($value)) {
                    throw new HttpException("Parameter '{$param}' must be an array", StatusCode::BAD_REQUEST);
                }

                $query = $value;
            }

            $instance = $factory->createEntities($class);
            $count = $instance->countEntities($query);

            $response->getBody()->write(json_encode(['count' => $count], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    /**
     * @param class-string<Entities> $class
     */
    public function getById(string $class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);

            if (!isset($args['id']) || !is_string($args['id'])) {
                throw new HttpException('Missing expected id argument');
            }

            /** @var array<string, string> $params */
            $params = $request->getQueryParams();
            $fields = isset($params['fields']) ? explode(',', $params['fields']) : [];
            $entity = $instance->getEntityById($args['id'], $fields);
            $response->getBody()->write(json_encode(['data' => $entity], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    /**
     * @param class-string<Entities> $class
     */
    public function insert(string $class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $data = $request->getParsedBody();

            if (!is_array($data)) {
                throw new HttpException('Failed to parse body');
            }

            if (isset($data[0])) {
                // bulk insert
                /** @var list<array> $data */
                $body = ['ids' => $instance->addEntities($data)];
            } else {
                $ids = $instance->addEntities([$data]);
                // no ID is returned for SQL Server views with an INSTEAD OF INSERT trigger
                $body = ['id' => $ids[0] ?? null];
            }

            $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    /**
     * @param class-string<Entities> $class
     */
    public function update(string $class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $body = $request->getParsedBody();

            if (!is_array($body)) {
                throw new HttpException('Failed to parse body');
            }

            if (!isset($args['id']) || !is_string($args['id'])) {
                throw new HttpException('Missing expected id argument');
            }

            $affected = $instance->updateById($args['id'], $body);
            $response->getBody()->write(json_encode(['affected' => $affected], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    /**
     * @param class-string<Entities> $class
     */
    public function patch(string $class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $body = $request->getParsedBody();

            if (!is_array($body)) {
                throw new HttpException('Failed to parse body');
            }

            if (!isset($args['id']) || !is_string($args['id'])) {
                throw new HttpException('Missing expected id argument');
            }

            $affected = $instance->patchByIds(explode(',', $args['id']), $body);
            $response->getBody()->write(json_encode(['affected' => $affected], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    /**
     * @param class-string<Entities> $class
     */
    public function delete(string $class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $_req, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);

            if (!isset($args['id']) || !is_string($args['id'])) {
                throw new HttpException('Missing expected id argument');
            }

            $affected = $instance->deleteByIds(explode(',', $args['id']));

            if ($affected === 0) {
                throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
            }

            return $response->withStatus(StatusCode::NO_CONTENT);
        };
    }
}
