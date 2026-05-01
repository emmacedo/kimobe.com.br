<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_upgrade_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('fullflow_subscription_id')->nullable();
            $table->string('trigger_module', 64);
            $table->unsignedInteger('required_amount')->nullable();
            $table->string('from_plan_code', 64)->nullable();
            $table->string('to_plan_code', 64)->nullable();
            $table->string('result', 32);
            $table->decimal('proration_amount', 12, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_upgrade_log');
    }
};
