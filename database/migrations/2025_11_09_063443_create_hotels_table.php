<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name',200);
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->tinyInteger('stars')->nullable();
            $table->integer('rooms_available')->default(0);
            $table->decimal('price_per_night', 10,2)->nullable();
            $table->string('currency',10)->default('CFA');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('hotels'); }
};
