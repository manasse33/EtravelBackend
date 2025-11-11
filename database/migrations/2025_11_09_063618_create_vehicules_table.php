<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('type',100); // minivan, car, bus, 4x4
            $table->integer('capacity')->default(4);
            $table->decimal('price_per_day', 10,2)->nullable();
            $table->string('currency',10)->default('CFA');
            $table->boolean('available')->default(true);
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('vehicules'); }
};
