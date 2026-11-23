<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('extension', 10)->nullable()->after('phone');
            $table->string('job_title')->nullable()->after('extension');
            $table->foreignId('department_id')->nullable()->after('job_title')->constrained()->nullOnDelete();
            $table->foreignId('default_room_id')->nullable()->after('department_id')->constrained('rooms')->nullOnDelete();
            $table->string('external_id')->nullable()->unique()->after('default_room_id');
            $table->char('locale', 2)->default('ar')->after('external_id');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('default_room_id');
            $table->dropUnique(['external_id']);
            $table->dropColumn(['phone', 'extension', 'job_title', 'external_id', 'locale', 'is_active', 'deleted_at']);
        });
    }
};
