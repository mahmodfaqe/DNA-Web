<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('preset', 40);
            $table->unsignedSmallInteger('cells');
            $table->unsignedSmallInteger('minutes');

            // The seed is a column of its own rather than a key inside the JSON
            // because it is the one value that makes a run repeatable, and
            // "find me the run with seed X" should not require a JSON query.
            $table->unsignedBigInteger('seed');

            $table->boolean('succeeded')->default(false);
            $table->json('result');
            $table->timestamps();

            $table->index('created_at');
            $table->index('preset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulations');
    }
};
