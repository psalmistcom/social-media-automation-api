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
        Schema::create('plan_instagram_posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instagram_page_id')->unsigned();
            $table->string('text');
            $table->dateTime('post_date');
            $table->string('image_link');
            $table->string('post_id_from_instagram')->nullable();
            $table->string('post_link')->nullable();
            $table->string('error_message')->nullable();
            $table->enum('is_published', [0, 1, 2])->default(0);
            $table->timestamps();
            $table->foreign('instagram_page_id')->references('id')->on('instagram_pages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_instagram_posts');
    }
};
