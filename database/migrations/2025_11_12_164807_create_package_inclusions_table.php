<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('package_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('ouikenac_packages')->cascadeOnDelete();
            $table->string('name', 150); // nom de l’inclusion
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('package_inclusions');
    }
};
