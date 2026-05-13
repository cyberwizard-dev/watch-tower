<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $projectIds = DB::table('error_groups')
            ->whereNull('display_number')
            ->distinct()
            ->pluck('project_id');

        foreach ($projectIds as $projectId) {
            $next = (int) (DB::table('error_groups')
                ->where('project_id', $projectId)
                ->max('display_number') ?? 0) + 1;

            $ids = DB::table('error_groups')
                ->where('project_id', $projectId)
                ->whereNull('display_number')
                ->orderBy('first_occurrence_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                DB::table('error_groups')
                    ->where('id', $id)
                    ->update(['display_number' => $next++]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
