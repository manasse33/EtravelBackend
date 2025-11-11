<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('taxis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('zone')->nullable(); // neighborhood / quartier
            $table->string('phone')->nullable();
            $table->decimal('base_fare', 10,2)->nullable();
            $table->decimal('fare_per_km', 10,2)->nullable();
            $table->string('currency',10)->default('CFA');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('taxis'); }
};
