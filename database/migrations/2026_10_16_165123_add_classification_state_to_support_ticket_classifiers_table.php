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
        Schema::table('support_ticket_classifiers', function (Blueprint $table) {
            $table->timestamp('classification_requested_at')->nullable();
            $table->text('classification_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_ticket_classifiers', function (Blueprint $table) {
            $table->dropColumn(['classification_requested_at', 'classification_error']);
        });
    }
};
