<?php

use Elyar\ServiceTokens\Models\ServiceToken;
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
        Schema::create('service_token_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ServiceToken::class)->constrained()->cascadeOnDelete();
            $table->string('method', 16);
            $table->string('uri');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_token_permissions');
    }
};
