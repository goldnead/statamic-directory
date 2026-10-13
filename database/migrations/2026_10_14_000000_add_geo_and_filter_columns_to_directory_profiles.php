<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('directory_profiles', 'lat')) {
                $table->double('lat')->nullable();
                $table->double('lng')->nullable();
                $table->index(['site', 'lat', 'lng']);
            }

            foreach (array_keys(config('directory.filters', [])) as $column) {
                if (! Schema::hasColumn('directory_profiles', $column)) {
                    $table->string($column)->nullable()->index();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('directory_profiles', function (Blueprint $table): void {
            $table->dropIndex(['site', 'lat', 'lng']);
            $table->dropColumn(array_merge(['lat', 'lng'], array_keys(config('directory.filters', []))));
        });
    }
};
