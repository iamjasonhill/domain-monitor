<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('email_usage')->nullable()->after('platform');
        });

        $now = now();
        $operationalWebOnlyPrefixes = ['app', 'admin', 'booking', 'bookings', 'portal', 'quoting'];

        DB::table('domains')
            ->select(['id', 'domain'])
            ->whereNull('email_usage')
            ->orderBy('domain')
            ->lazy()
            ->each(function (object $record) use ($operationalWebOnlyPrefixes, $now): void {
                if (! $this->shouldBackfillAsWebOnlySubdomain((string) $record->domain, $operationalWebOnlyPrefixes)) {
                    return;
                }

                DB::table('domains')
                    ->where('id', $record->id)
                    ->update([
                        'email_usage' => 'none',
                        'updated_at' => $now,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('email_usage');
        });
    }

    /**
     * @param  array<int, string>  $operationalWebOnlyPrefixes
     */
    private function shouldBackfillAsWebOnlySubdomain(string $domain, array $operationalWebOnlyPrefixes): bool
    {
        $host = strtolower(trim($domain));
        $labels = array_values(array_filter(explode('.', $host), static fn (string $label): bool => $label !== ''));

        if ($labels === [] || ! $this->isSubdomainHostname($labels)) {
            return false;
        }

        return in_array($labels[0], $operationalWebOnlyPrefixes, true);
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function isSubdomainHostname(array $labels): bool
    {
        $registrableLabelCount = 2;
        $compoundPublicSuffixes = [
            'asn.au',
            'com.au',
            'edu.au',
            'gov.au',
            'id.au',
            'net.au',
            'org.au',
        ];
        $host = implode('.', $labels);

        foreach ($compoundPublicSuffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                $registrableLabelCount = 3;

                break;
            }
        }

        return count($labels) > $registrableLabelCount;
    }
};
