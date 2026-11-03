<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table): void {
            $table->id();
            $table->char('key_hash', 64)->unique();
            $table->char('scope_hash', 64);
            $table->char('cart_hash', 64);
            $table->char('payload_hash', 64)->nullable();
            // Keep the binding even when an owner account is deleted.
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('merchant_context', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('amount_unit', 16);
            // No provider conversion contract is assumed in P2.
            $table->string('gateway_amount', 40)->nullable();
            $table->string('gateway_amount_unit', 16)->nullable();
            $table->string('status', 24)->index();
            $table->char('initiation_key', 64)->unique();
            $table->string('authority', 128)->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->unique(['provider', 'authority']);
            $table->unique(['provider', 'provider_reference']);
            // Nullable unique slots: at most one live attempt and one settlement/order.
            $table->foreignId('active_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('settlement_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->string('source', 32)->nullable();
            $table->string('verification_outcome', 32)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('actor_snapshot')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('manual_reference', 255)->nullable();
            $table->text('confirmation_reason')->nullable();
            $table->json('diagnostics')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('checkout_intents');
    }
};
