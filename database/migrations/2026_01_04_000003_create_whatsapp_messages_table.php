<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')
                ->constrained('whatsapp_conversations')
                ->cascadeOnDelete();
            $table->string('meta_message_id', 191)->nullable()->unique();
            $table->string('direction', 20); // inbound, outbound
            $table->string('message_type', 30); // text, template, image, document, audio, interactive
            $table->text('body')->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('status', 30)->default('queued'); // queued, sent, delivered, read, failed
            $table->boolean('is_coexistence_echo')->default(false);
            $table->json('error_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'sent_at'], 'idx_wm_conv_sent');
            $table->index(['organization_id', 'status'], 'idx_wm_org_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
