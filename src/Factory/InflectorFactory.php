<?php declare(strict_types=1);

namespace SwagGraphQL\Factory;


use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory as DoctrineInflectorFactory;

class InflectorFactory
{
    private readonly Inflector $inflector;

    public function __construct()
    {
        $this->inflector = DoctrineInflectorFactory::create()->build();
    }

    public function getInflector(): Inflector
    {
        return $this->inflector;
    }
}
