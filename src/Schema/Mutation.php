<?php declare(strict_types=1);

namespace SwagGraphQL\Schema;

use Doctrine\Inflector\Inflector;
use SwagGraphQL\Factory\InflectorFactory;

class Mutation
{
    public const ACTION_DELETE = 'delete';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';

    public const ACTION_UPSERT = 'upsert';

    private static Inflector $inflector;

    private string $action;

    private string $entityName;

    public static function fromName(string $name): Mutation
    {
        if (strpos($name, static::ACTION_CREATE) === 0) {
            return new self(static::ACTION_CREATE, self::$inflector->tableize(substr($name, strlen(static::ACTION_CREATE))));
        }
        if (strpos($name, static::ACTION_UPDATE) === 0) {
            return new self(static::ACTION_UPDATE, self::$inflector->tableize(substr($name, strlen(static::ACTION_UPDATE))));
        }
        if (strpos($name, static::ACTION_DELETE) === 0) {
            return new self(static::ACTION_DELETE, self::$inflector->tableize(substr($name, strlen(static::ACTION_DELETE))));
        }

        throw new \Exception('Mutation without valid action prefix called, got: ' . $name);
    }

    public function __construct(string $action, string $entityName)
    {
        $this->action = $action;
        $this->entityName = $entityName;
        self::$inflector = (new InflectorFactory())->getInflector();
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getName(): string
    {
        return $this->action . self::$inflector->classify($this->entityName);
    }
}
