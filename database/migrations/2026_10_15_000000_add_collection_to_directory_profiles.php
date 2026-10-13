<?php

use Goldnead\Directory\Services\Collections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('directory_profiles', 'collection')) {
            return;
        }

        // Existing rows were projected from the first (formerly only) collection.
        Schema::table('directory_profiles', function (Blueprint $table): void {
            $table->string('collection')->default(Collections::default())->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('directory_profiles', 'collection')) {
            Schema::table('directory_profiles', function (Blueprint $table): void {
                $table->dropIndex(['collection']);
                $table->dropColumn('collection');
            });
        }
    }
};
