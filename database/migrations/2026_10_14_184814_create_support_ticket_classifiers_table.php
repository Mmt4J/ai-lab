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
        Schema::create('support_ticket_classifiers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();

            $table->string('ticket_subject')->nullable();
            $table->text('ticket_message')->nullable();

            $table->string('ticket_category')->nullable();
            $table->string('urgency_level')->nullable();
            
            $table->text('ai_summary')->nullable();
            
            $table->string('status')->default('open');

            $table->timestamp('classified_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_category', 'urgency_level', 'status'], 'support_ticket_classifier_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_classifiers');
    }
};
