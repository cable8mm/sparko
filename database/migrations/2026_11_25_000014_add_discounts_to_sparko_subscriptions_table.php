<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sparko_subscriptions', function (Blueprint $table): void {
            $table->string('discount_code')->nullable()->after('credit_balance');
            $table->string('discount_type')->nullable()->after('discount_code');
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->string('discount_duration')->nullable()->after('discount_value');
            $table->unsignedInteger('discount_remaining_charges')->nullable()->after('discount_duration');
            $table->timestamp('discount_expires_at')->nullable()->after('discount_remaining_charges');
        });
    }

    public function down(): void
    {
        Schema::table('sparko_subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'discount_code',
                'discount_type',
                'discount_value',
                'discount_duration',
                'discount_remaining_charges',
                'discount_expires_at',
            ]);
        });
    }
};
