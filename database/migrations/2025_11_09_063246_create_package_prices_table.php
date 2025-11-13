<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            // polymorphic relation to any package table
            $table->morphs('priceable'); // priceable_type, priceable_id
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete(); // price context country (e.g., currency/region)
            $table->foreignId('departure_country_id')->nullable()->constrained('countries')->nullOnDelete(); // optional route-specific
            $table->foreignId('arrival_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->integer('min_people')->default(1);
            $table->integer('max_people')->nullable();
            $table->decimal('price', 13, 2);
            $table->string('programme')->nullable();
            $table->enum('currency', ['CFA', 'USD', 'EUR'])->default('CFA');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('package_prices'); }
};
