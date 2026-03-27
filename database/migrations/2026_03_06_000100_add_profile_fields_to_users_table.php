<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('lastname');
            $table->string('facebook_url')->nullable()->after('bio');
            $table->string('avatar')->nullable()->after('facebook_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'facebook_url', 'avatar']);
        });
    }
};
