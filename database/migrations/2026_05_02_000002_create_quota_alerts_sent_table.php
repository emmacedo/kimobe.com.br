<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_alerts_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module_slug', 64);
            $table->unsignedSmallInteger('threshold');
            $table->string('period_marker', 64)->nullable();
            $table->timestamp('triggered_at')->useCurrent();

            $table->index(['tenant_id', 'module_slug', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_alerts_sent');
    }
};
