<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scheduled-jobs', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->enum('frequency', ['daily', 'weekly', 'bi-weekly', 'monthly', 'quarterly']);
            $table->time('scheduled_time')->default('08:00:00');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('scheduled_reports');
    }
};