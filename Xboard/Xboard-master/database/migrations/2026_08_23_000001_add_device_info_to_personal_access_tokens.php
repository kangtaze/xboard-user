<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add device/session metadata columns to personal_access_tokens so the
     * Flutter client can display the active session list with IP / OS / device.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // IP address captured at login (subject to X-Forwarded-For when behind proxy)
            $table->string('ip', 45)->nullable()->after('expires_at');
            // Parsed from User-Agent at login time. Stored as plain strings to keep
            // the table light and avoid pulling a heavy UA library into the model.
            $table->string('os', 64)->nullable()->after('ip');
            $table->string('device', 64)->nullable()->after('os');
            $table->string('browser', 64)->nullable()->after('device');
            // Reserved for a future GeoIP lookup; not populated yet.
            $table->string('country', 8)->nullable()->after('browser');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip', 'os', 'device', 'browser', 'country']);
        });
    }
};
