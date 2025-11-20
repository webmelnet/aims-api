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
        Schema::create('asset_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_out_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_out_at');
            $table->timestamp('expected_return_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('checkout_notes')->nullable();
            $table->text('checkin_notes')->nullable();
            $table->enum('condition_out', [
                'excellent',
                'good',
                'fair',
                'poor',
                'damaged'
            ])->default('good');
            $table->enum('condition_in', [
                'excellent',
                'good',
                'fair',
                'poor',
                'damaged'
            ])->nullable();
            $table->enum('status', ['checked_out', 'checked_in', 'overdue'])->default('checked_out');
            $table->timestamps();
            
            $table->index(['asset_id', 'status']);
            $table->index('user_id');
            $table->index('checked_out_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_checkouts');
    }
};
