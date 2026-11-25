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
            $table->unsignedInteger('failed_attempts')->default(0)->after('credit_balance');
        });
    }

    public function down(): void
    {
        Schema::table('sparko_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('failed_attempts');
        });
    }
};
