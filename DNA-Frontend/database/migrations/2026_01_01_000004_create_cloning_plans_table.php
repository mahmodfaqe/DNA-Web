<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloning_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label')->nullable();
            $table->unsignedInteger('template_length');
            $table->string('panel', 40);
            $table->boolean('circular')->default(false);
            $table->string('forward_enzyme', 40)->nullable();
            $table->string('reverse_enzyme', 40)->nullable();
            $table->boolean('succeeded')->default(false);
            $table->json('result');
            $table->timestamps();

            // Pruning scans by age, and the tab's own list is newest-first.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloning_plans');
    }
};
