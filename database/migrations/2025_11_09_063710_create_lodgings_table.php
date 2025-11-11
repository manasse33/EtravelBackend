<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('lodgings', function (Blueprint $table) {
            $table->id();
            $table->string('title',200);
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->decimal('price_per_night',10,2)->nullable();
            $table->string('currency',10)->default('CFA');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('lodgings'); }
};
