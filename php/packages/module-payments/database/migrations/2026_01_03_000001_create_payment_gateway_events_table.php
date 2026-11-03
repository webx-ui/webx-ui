<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Payments\Exchange\ExchangeConnection;

/**
 * The journal of the exchange with gateways (spec §20.2): one row per event — the raw half written
 * once before anything is parsed, the decision added once after. Created on the journal's own
 * connection, the one it writes through (decision 38).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return app(ExchangeConnection::class)->name();
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->create('payment_gateway_events', function (Blueprint $table): void {
            $table->id();
            $table->char('trace', 26)->index();
            $table->string('direction', 8);
            $table->string('kind', 16);
            $table->string('gateway', 64)->nullable();
            $table->string('method_key', 64)->nullable();
            // No foreign keys: an event outlives any error of a link, and the order's.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('http_method', 8)->nullable();
            $table->text('url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('request_headers')->nullable();
            $table->binary('request_body')->nullable();
            $table->unsignedInteger('request_size')->nullable();
            $table->char('request_sha256', 64)->nullable();
            $table->boolean('request_truncated')->default(false);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->binary('response_body')->nullable();
            $table->unsignedInteger('response_size')->nullable();
            $table->char('response_sha256', 64)->nullable();
            $table->boolean('response_truncated')->default(false);
            $table->string('transport_error', 500)->nullable();
            $table->timestamp('started_at', 3)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('masked')->nullable();
            $table->char('event_key', 64)->nullable();
            $table->string('signature', 12)->nullable();
            $table->string('reported', 16)->nullable();
            $table->string('status_before', 16)->nullable();
            $table->string('status_after', 16)->nullable();
            $table->string('outcome', 16)->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            // The tab of an order and the screen of an attempt, newest first by id.
            $table->index(['payment_id', 'id']);
            $table->index(['order_id', 'id']);
            $table->index(['gateway', 'created_at']);
            $table->index(['kind', 'created_at']);
            // «Signature errors» and «errors» of the journal screen (§20.7).
            $table->index(['signature', 'created_at']);
            $table->index(['outcome', 'created_at']);
            // Repeats of a notification (§9.2, step 5) — not unique: a repeat is an event too (decision 41).
            $table->index(['method_key', 'event_key']);
        });

        // A body is kept up to 1 MB and may be set up to 16 (§20.4): a plain BLOB stops at 64 KB.
        $connection = DB::connection($this->getConnection());

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $connection->statement('ALTER TABLE '.$connection->getTablePrefix().'payment_gateway_events MODIFY request_body MEDIUMBLOB NULL, MODIFY response_body MEDIUMBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('payment_gateway_events');
    }
};
