<?php

declare(strict_types=1);

namespace App\Sparko\Presentation;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class KoreanFormatter
{
    public function currency(int $amount): string
    {
        return '₩'.number_format($amount);
    }

    public function date(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->locale('ko')->translatedFormat('Y년 n월 j일');
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value)->locale('ko')->translatedFormat('Y년 n월 j일');
        }

        return '';
    }
}
