<?php
declare(strict_types=1);

namespace Sserbin\TwigLinter;

use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class StubEnvironment extends Environment
{
    /**
     * {@inheritdoc}
     */
    public function getFilter($name)
    {
        return new TwigFilter((string)$name, $this->noop(), [
            'is_variadic' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFunction($name)
    {
        return new TwigFunction((string)$name, $this->noop(), [
            'is_variadic' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTest($name)
    {
        return new TwigTest((string)$name, $this->noop(), [
            'is_variadic' => true,
        ]);
    }

    private function noop(): callable
    {
        /**
         * @param mixed $_
         */
        return function ($_, array $arg = []): void {
        };
    }
}
