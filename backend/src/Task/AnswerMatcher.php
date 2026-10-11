<?php

declare(strict_types=1);

namespace App\Task;

final class AnswerMatcher
{
    public static function isNumber(string $value): bool
    {
        return preg_match('/^[+-]?[0-9]+(?:[.,][0-9]+)?$/D', trim($value)) === 1;
    }

    public static function matches(AnswerType $type, string $expected, string $submitted): bool
    {
        if ($type === AnswerType::NUMBER) {
            $left = self::normaliseNumber($expected);
            $right = self::normaliseNumber($submitted);
            return $left !== null && $right !== null && $left === $right;
        }

        if ($type === AnswerType::TEXT) {
            return mb_strtolower(trim($expected)) === mb_strtolower(trim($submitted));
        }

        if ($type === AnswerType::MULTIPLE_CHOICE) {
            return $expected === $submitted;
        }

        return false;
    }

    private static function normaliseNumber(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!self::isNumber($value)) return null;
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');
        $normal = $integer.($fraction === '' ? '' : '.'.$fraction);
        return $negative && $normal !== '0' ? '-'.$normal : $normal;
    }
}
