<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('deliveries')
            ->whereNull('tracking_token')
            ->select('id')
            ->chunkById(200, function ($deliveries): void {
                foreach ($deliveries as $delivery) {
                    DB::table('deliveries')
                        ->where('id', $delivery->id)
                        ->update(['tracking_token' => Str::random(40)]);
                }
            });
    }

    public function down(): void
    {
        // Backfill is not reversible: tokens may already be in use for tracking links.
    }
};
