<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->enum('package_type', ['ouikenac', 'destination', 'city_tour']);
            $table->string('sub_type', 50)->nullable(); // libota, premium, acces
            $table->enum('country', ['RC', 'RDC'])->nullable(); // NULL si package destination
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
