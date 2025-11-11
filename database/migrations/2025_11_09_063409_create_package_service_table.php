<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('package_service', function (Blueprint $table) {
            $table->id();
            $table->morphs('packageable'); // packageable_type, packageable_id (destination_packages, ouikenac_packages, city_tours)
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->text('details')->nullable(); // e.g., "includes breakfast", "transfer included"
            $table->timestamps();
            $table->unique(['packageable_type','packageable_id','service_id'], 'pkg_srv_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('package_service'); }
};
