<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_id');
            $table->string('site');
            $table->string('legacy_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title');
            $table->string('city')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('visibility')->default('private');
            $table->boolean('hidden')->default(false);
            $table->boolean('published')->default(false);
            $table->timestamps();

            $table->unique(['entry_id', 'site']);
            $table->unique(['legacy_id', 'site']);
            $table->unique(['team_id', 'site']);
            $table->index(['site', 'published', 'hidden', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_profiles');
    }
};
