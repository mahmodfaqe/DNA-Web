<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_designs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('signal', 40);
            $table->string('chassis', 40);
            // The verdict is a column rather than a JSON key: "show me every
            // design that chose a recombinase" is the question this table will
            // actually be asked.
            $table->string('architecture', 40)->nullable();
            $table->unsignedSmallInteger('hold_hours');
            $table->boolean('succeeded')->default(false);
            $table->json('result');
            $table->timestamps();

            $table->index('created_at');
            $table->index('architecture');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_designs');
    }
};
