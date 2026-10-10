<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OTP challenges. The code itself is stored only as a hash.
     */
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->char('phone', 11);
            $table->string('purpose', 32);
            $table->char('code_hash', 64);
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->dateTime('consumed_at')->nullable();
            $table->foreignId('signup_draft_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('athlete_invitation_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('created_at');

            $table->index(['phone', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
