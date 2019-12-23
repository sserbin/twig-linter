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
    public function getFilter($name): ?TwigFilter
    {
        /**
         * @var string[]
         * @psalm-suppress InternalMethod
         */
        $defaultFilters = array_keys(parent::getFilters());
        $isDefault = isset($defaultFilters[$name]);

        if ($isDefault) { // don't attempt to stub twig's builtin filter
            /** @psalm-suppress InternalMethod */
            return parent::getFilter($name);
        }

        return new TwigFilter((string)$name, $this->noop(), [
            'is_variadic' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFunction($name): ?TwigFunction
    {
        /**
         * @var string[]
         * @psalm-suppress InternalMethod
         */
        $defaultFunctions = array_keys(parent::getFunctions());
        $isDefault = isset($defaultFunctions[$name]);

        if ($isDefault) { // don't attempt to stub twig's builtin function
            /** @psalm-suppress InternalMethod */
            return parent::getFunction($name);
        }

        return new TwigFunction((string)$name, $this->noop(), [
            'is_variadic' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTest($name): ?TwigTest
    {
        /**
         * @var string[]
         * @psalm-suppress InternalMethod
         */
        $defaultTests = array_keys(parent::getTests());
        $isDefault = isset($defaultTests[$name]) || $this->listContainsSubstring($defaultTests, $name);

        if ($isDefault) { // don't attempt to stub twig's builtin test
            /** @psalm-suppress InternalMethod */
            $parentTest = parent::getTest($name);

            if ($parentTest instanceof TwigTest) {
                return $parentTest;
            }

            // In twig 2.x this can return `false`.
            // Lets just force it here as null because
            // of the added typehint for Twig 3.x
            return null;
        }

        return new TwigTest((string)$name, $this->noop(), [
            'is_variadic' => true,
        ]);
    }

    private function noop(): callable
    {
        /**
         * @param mixed $_
         * @param array $arg
         */
        return function ($_ = null, array $arg = []): void {
        };
    }

    /**
     * @param string[] $list
     * @return bool
     */
    private function listContainsSubstring(array $list, string $needle): bool
    {
        foreach ($list as $item) {
            if (false !== strpos($item, $needle)) {
                return true;
            }
        }
        return false;
    }
}
