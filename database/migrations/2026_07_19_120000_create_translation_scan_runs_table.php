<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capell_translation_scan_runs')) {
            return;
        }

        Schema::create('capell_translation_scan_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('source_key');
            $table->string('source_locale', 20);
            $table->string('status', 20)->default('queued');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'source_key', 'source_locale', 'status'], 'translation_scan_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capell_translation_scan_runs');
    }
};
