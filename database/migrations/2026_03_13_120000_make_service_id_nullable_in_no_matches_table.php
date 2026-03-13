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
        Schema::table('no_matches', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreignId('service_id')->nullable()->change();
            $table->foreign('service_id')->references('id')->on('services');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('no_matches', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreignId('service_id')->nullable(false)->change();
            $table->foreign('service_id')->references('id')->on('services');
        });
    }
};
