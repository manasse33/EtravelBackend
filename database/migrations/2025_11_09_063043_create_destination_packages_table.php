<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('destination_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title',150);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // route-based: departure city / arrival city
            $table->foreignId('departure_country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('arrival_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('destination_packages'); }
};
