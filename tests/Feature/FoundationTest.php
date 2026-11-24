<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_required_stores_are_configured(): void
    {
        $this->assertSame('database', config('queue.default'));
        $this->assertSame('database', config('cache.default'));
        $this->assertSame('log', config('mail.default'));
    }

    public function test_plan_and_discount_configuration_files_load(): void
    {
        $this->assertIsArray(config('plans.plans'));
        $this->assertSame(5, config('plans.default_trial_days'));
        $this->assertIsArray(config('discounts.codes'));
    }

    public function test_configured_mail_transport_delivers_a_test_message(): void
    {
        $subject = 'Sparko mail transport check '.uniqid('', true);

        Mail::raw('Foundation transport verification.', function ($message) use ($subject): void {
            $message->to('check@example.com')->subject($subject);
        });

        $logs = glob(storage_path('logs/laravel*.log')) ?: [];
        $contents = '';
        foreach ($logs as $log) {
            $contents .= (string) file_get_contents($log);
        }

        $this->assertStringContainsString($subject, $contents);
    }
}
