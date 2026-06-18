<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE ebooks MODIFY cover_image_url VARCHAR(500) NULL');
            DB::statement('ALTER TABLE ebooks MODIFY pdf_file_path VARCHAR(500) NULL');
            DB::statement('ALTER TABLE ebooks MODIFY pdf_file_size BIGINT UNSIGNED NULL');
            return;
        }
        Schema::table('ebooks', function (Blueprint $table) {
            $table->string('cover_image_url', 500)->nullable()->change();
            $table->string('pdf_file_path', 500)->nullable()->change();
            $table->unsignedBigInteger('pdf_file_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE ebooks MODIFY cover_image_url VARCHAR(500) NOT NULL');
            DB::statement('ALTER TABLE ebooks MODIFY pdf_file_path VARCHAR(500) NOT NULL');
            DB::statement('ALTER TABLE ebooks MODIFY pdf_file_size BIGINT UNSIGNED NOT NULL');
            return;
        }
        Schema::table('ebooks', function (Blueprint $table) {
            $table->string('cover_image_url', 500)->nullable(false)->change();
            $table->string('pdf_file_path', 500)->nullable(false)->change();
            $table->unsignedBigInteger('pdf_file_size')->nullable(false)->change();
        });
    }
};
