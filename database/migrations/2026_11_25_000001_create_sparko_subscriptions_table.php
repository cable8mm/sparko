<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sparko_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('billable_type');
            $table->string('billable_id');
            $table->string('plan_id');
            $table->string('billing_key_issue_id');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->index(['billable_type', 'billable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sparko_subscriptions');
    }
};
