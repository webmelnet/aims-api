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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique(); // Unique identifier
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('asset_categories')->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('barcode')->nullable();
            $table->text('qr_code')->nullable(); // Store QR code image path
            
            // Purchase Information
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->string('purchase_order')->nullable();
            
            // Warranty Information
            $table->date('warranty_expiry_date')->nullable();
            $table->integer('warranty_months')->nullable();
            
            // Depreciation
            $table->decimal('current_value', 12, 2)->nullable();
            $table->decimal('salvage_value', 12, 2)->nullable();
            $table->integer('useful_life_years')->nullable();
            $table->string('depreciation_method')->nullable(); // straight-line, declining-balance
            
            // Location and Assignment
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            
            // Status and Condition
            $table->enum('status', [
                'available',
                'in_use',
                'maintenance',
                'repair',
                'retired',
                'disposed',
                'lost',
                'stolen'
            ])->default('available');
            $table->enum('condition', [
                'excellent',
                'good',
                'fair',
                'poor',
                'damaged'
            ])->default('good');
            
            // Additional Information
            $table->text('specifications')->nullable(); // JSON field for custom specs
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->date('next_maintenance_date')->nullable();
            $table->date('disposal_date')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for better performance
            $table->index('asset_tag');
            $table->index('serial_number');
            $table->index('status');
            $table->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
