<?php

declare(strict_types=1);

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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('one_time_passwords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('purpose');
            $table->string('code_hash');
            $table->string('device_id_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'purpose']);
            $table->index('expires_at');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
