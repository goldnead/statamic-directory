<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foreignKeys = Schema::getForeignKeys('directory_profiles');
        $hasTeamForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['team_id']
        );

        if (! $hasTeamForeignKey) {
            DB::table('directory_profiles')
                ->whereNotNull('team_id')
                ->whereNotIn('team_id', DB::table('teams')->select('id'))
                ->update(['team_id' => null]);

            Schema::table('directory_profiles', function (Blueprint $table): void {
                $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $foreignKeys = Schema::getForeignKeys('directory_profiles');
        $hasTeamForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['team_id']
        );

        if ($hasTeamForeignKey) {
            Schema::table('directory_profiles', function (Blueprint $table): void {
                $table->dropForeign(['team_id']);
            });
        }
    }
};
