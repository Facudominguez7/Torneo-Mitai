<?php

use App\Models\NotificationDispatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->string('channel', 32);
            $table->string('provider', 32)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('recipient_email')->nullable();
            $table->enum('status', NotificationDispatch::STATUSES);
            $table->timestamp('status_updated_at')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('notification_type');
            $table->index('status');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
