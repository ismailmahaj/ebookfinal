<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ebooks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author');
            $table->text('description');
            $table->string('isbn', 50)->nullable()->unique();
            $table->string('cover_image_url', 500);
            $table->string('pdf_file_path', 500);
            $table->unsignedBigInteger('pdf_file_size');
            $table->unsignedInteger('total_pages');
            $table->unsignedInteger('preview_pages')->default(10);
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slug');
            $table->index('author');
            $table->index('is_featured');
            $table->index('is_active');
            $table->index('published_at');
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'author', 'description']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebooks');
    }
};
