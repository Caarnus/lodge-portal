<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->orderBy('id')->chunkById(100, function ($events): void {
            $now = now();
            $rules = [];

            foreach ($events as $event) {
                foreach ([10080, 1440, 60] as $offsetMinutes) {
                    $rules[] = [
                        'event_id' => $event->id,
                        'lodge_id' => $event->lodge_id,
                        'offset_minutes' => $offsetMinutes,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('event_reminder_rules')->insertOrIgnore($rules);
        });
    }

    public function down(): void {}
};
