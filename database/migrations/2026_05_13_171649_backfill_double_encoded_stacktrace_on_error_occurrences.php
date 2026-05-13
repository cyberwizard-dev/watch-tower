<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('error_occurrences')
            ->select('id', 'stacktrace')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $value = json_decode($row->stacktrace, true);

                    // If the column held a JSON string (double-encoded), $value
                    // will itself be a string of JSON. Decode once more to lift
                    // it back into an array.
                    if (is_string($value)) {
                        $inner = json_decode($value, true);

                        if (is_array($inner)) {
                            DB::table('error_occurrences')
                                ->where('id', $row->id)
                                ->update(['stacktrace' => json_encode($inner)]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};
