<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ouikenac_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title',150);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // config: departure country/city and arrival country/city (arrival may be same country)
            $table->foreignId('departure_country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('departure_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('arrival_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('arrival_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->integer('min_people')->nullable();
            $table->integer('max_people')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ouikenac_packages'); }
};
