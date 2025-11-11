<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            // polymorphic target: reservation can be for destination_package, ouikenac_package, city_tour, hotel, taxi, vehicle, lodging
            $table->morphs('reservable'); // reservable_type, reservable_id
            $table->string('full_name',150);
            $table->string('email',150);
            $table->string('phone',50)->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->integer('travelers')->default(1);
            $table->decimal('total_price', 13,2)->nullable();
            $table->string('currency',10)->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending','approved','rejected','cancelled'])->default('pending');
            $table->foreignId('validated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reservations'); }
};
