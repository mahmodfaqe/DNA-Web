<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->index();
            $table->unsignedInteger('gene_count')->default(0);
            $table->json('payload');
            $table->timestamps();

            // Supports the retention sweep that removes stale uploads.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
