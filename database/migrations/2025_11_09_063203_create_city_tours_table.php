<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('city_tours', function (Blueprint $table) {
            $table->id();
            $table->string('title',150);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->date('scheduled_date')->nullable(); // date of the tour (many tours => create many records)
            $table->integer('min_people')->nullable();
            $table->integer('max_people')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('city_tours'); }
};
