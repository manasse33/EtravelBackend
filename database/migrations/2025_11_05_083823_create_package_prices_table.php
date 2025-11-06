<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->enum('country', ['RC', 'RDC'])->nullable();
            $table->integer('min_people')->default(1);
            $table->integer('max_people')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('CFA');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_prices');
    }
};
