<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

final class PropertyPathParser
{
    private const MAX_DEPTH = 8;

    public static function parse(string $raw): PropertyPath
    {
        $parts = array_values(array_filter(
            explode('.', trim($raw)),
            static fn(string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            throw new \InvalidArgumentException('Property path cannot be empty.');
        }

        return self::parseParts($parts, 0, 0);
    }

    public static function rootCode(string $raw): string
    {
        return self::parse($raw)->code;
    }

    public static function toPropString(PropertyPath $path): string
    {
        $result = $path->code;
        if ($path->autoResolve) {
            $result .= '.VALUE';
        }

        foreach ($path->nested as $nested) {
            $result .= '.' . self::toPropString($nested);
        }

        return $result;
    }

    /**
     * @param list<string> $parts
     */
    private static function parseParts(array $parts, int $offset, int $depth): PropertyPath
    {
        if ($depth >= self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Property path exceeds max depth.');
        }

        if (!isset($parts[$offset])) {
            throw new \InvalidArgumentException('Property path segment is missing.');
        }

        $code = $parts[$offset];
        ++$offset;

        $autoResolve = false;
        if (($parts[$offset] ?? null) === 'VALUE') {
            $autoResolve = true;
            ++$offset;
        }

        if ($offset >= count($parts)) {
            return new PropertyPath($code, $autoResolve);
        }

        return new PropertyPath(
            code: $code,
            autoResolve: $autoResolve,
            nested: [self::parseParts($parts, $offset, $depth + 1)],
        );
    }
}
