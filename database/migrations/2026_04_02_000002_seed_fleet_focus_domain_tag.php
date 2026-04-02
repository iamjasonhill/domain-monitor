<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private string $tagName = 'fleet.live';

    private string $tagDescription = 'Fleet-managed live domains used for the dedicated Fleet working set view.';

    public function up(): void
    {
        $now = now();

        $tag = DB::table('domain_tags')
            ->where('name', $this->tagName)
            ->first();

        if ($tag) {
            DB::table('domain_tags')
                ->where('id', $tag->id)
                ->update([
                    'priority' => 95,
                    'color' => '#2563EB',
                    'description' => $this->tagDescription,
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('domain_tags')->insert([
                'id' => (string) Str::uuid(),
                'name' => $this->tagName,
                'priority' => 95,
                'color' => '#2563EB',
                'description' => $this->tagDescription,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }

    public function down(): void
    {
        $tag = DB::table('domain_tags')
            ->where('name', $this->tagName)
            ->first();

        if (! $tag) {
            return;
        }

        $hasMemberships = DB::table('domain_tag')
            ->where('tag_id', $tag->id)
            ->exists();

        if ($hasMemberships) {
            return;
        }

        DB::table('domain_tags')
            ->where('id', $tag->id)
            ->where('description', $this->tagDescription)
            ->delete();
    }
};
