<?php

declare(strict_types=1);

namespace Digitalize\Sms\Models;

/** @internal */
final class Dates
{
    public static function parse(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
