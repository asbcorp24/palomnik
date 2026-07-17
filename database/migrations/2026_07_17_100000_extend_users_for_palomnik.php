<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('pilgrim')->after('password')->index();
            $table->string('phone', 32)->nullable()->after('email')->unique();
            $table->string('avatar_path')->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('avatar_path');
            $table->json('preferences')->nullable()->after('birth_date');
            $table->boolean('is_active')->default(true)->after('preferences')->index();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'phone',
                'avatar_path',
                'birth_date',
                'preferences',
                'is_active',
            ]);
        });
    }
};
