<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Sparko\Presentation\KoreanFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class KoreanFormatterTest extends TestCase
{
    public function test_currency_is_formatted_in_krw(): void
    {
        $formatter = app(KoreanFormatter::class);

        $this->assertSame('₩10,000', $formatter->currency(10000));
        $this->assertSame('₩0', $formatter->currency(0));
    }

    public function test_dates_are_formatted_for_korean_users(): void
    {
        $formatter = app(KoreanFormatter::class);

        $this->assertSame('2026년 11월 26일', $formatter->date(CarbonImmutable::parse('2026-11-26 12:00:00')));
        $this->assertSame('2026년 11월 26일', $formatter->date('2026-11-26 12:00:00'));
        $this->assertSame('', $formatter->date(null));
    }
}
