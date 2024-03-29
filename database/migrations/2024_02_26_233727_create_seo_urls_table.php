<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_urls', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->morphs('model');
            $table->enum('redirect', [null, 301, 302])->nullable()->default(null);
            $table->foreignId('redirect_to_seo_url_id')->nullable()->default(null)
                ->constrained('seo_urls', 'id', 'parent_id')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('route_name');
            $table->json('route_parameters');
            $table->boolean('is_canonical')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_urls');
    }
};
