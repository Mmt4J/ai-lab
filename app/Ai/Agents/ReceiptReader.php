<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

// Configure the agent to use Groq provider with Qwen 2.7B vision model
// Qwen 2.7B is a multimodal model that can process both images and text
#[Provider(Lab::Groq)]
#[Model('qwen/qwen3.8-27b')]
class ReceiptReader implements Agent, HasStructuredOutput
{
    // Promptable trait provides the prompt() method for sending requests to the AI
    use Promptable;

    // System instructions that guide the AI on how to process the receipt
    // This runs as the "system" role message before the user prompt
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are an expert financial transaction receipt parser. Extract structured data from bank transaction receipts (transfers, airtime purchases, bill payments, POS, withdrawals, etc.).

        Focus on Nigerian banking receipts (NIBSS, bank apps, USSD, mobile banking). Return ONLY valid JSON matching the schema. Do not include any extra commentary.

        NORMALIZE transaction_type to ONE of these standard categories (map variations):
        - "outward_transfer" - money sent OUT (TRANSFER OUT, NIP TRF, TRF OUT, E-TRANSFER OUT, TRANSFER-OUT, TRANSFER (OUT), TRANSFER DEBIT, TRF, INT TRF SEND, TRANSFER-OUT (E-COM), TRANSFER-OUT (TAX), TRANSFER DEBIT (E-COM), TRANSFER DEBIT (TAX), TRANSFER (E-COM), TRANSFER (DEBIT), TRANSFER TAX, TRANSFER PAYOUT, TRANSFER (PAYOUT), TRANSFER TO E-COM, TRANSFER (PAY OUT))
        - "inward_transfer" - money received IN (TRANSFER IN, E-TRANSFER IN, TRANSFER (IN), TRANSFER INTO ACCOUNT, TRANSFER INTO E-COM, TRANSFER INTO INT ACCOUNT, TRANSFER (PAY IN), TRANSFER RECEIPTS, INTERNAL TRANSFER, TRANSFER (INT), TRANSFER (INTERNAL), INT TRF, INT TRF (SEND), E-TRF IN, INT TRF E-COM, INTERNL INT-TRF, TRANSFER INTRANSIT)
        - "internal_transfer" - between own accounts (TRANSFER (INTER-ACCOUNT), TRANSFER-OUT (INTERNAL), TRANSFER TO INT ACCOUNT, TRANSFER TO INT)
        - "airtime" - airtime/data purchase (AIRTIME, DATA, RECHARGE)
        - "bill_payment" - bills (BILL PAYMENT, ELECTRICITY, TV, INTERNET, UTILITIES)
        - "pos" - POS transactions (POS, POINT OF SALE)
        - "withdrawal" - ATM/cash out (WITHDRAWAL, ATM, CASH OUT)
        - "deposit" - cash/cheque deposit (DEPOSIT, CASH DEPOSIT, CHEQUE DEPOSIT)

        Other fields:
        - status: "successful" | "pending" | "failed" (normalize case)
        - recipient/sender: name, account (may be masked), bank
        - amount, fee, stamp_duty: numbers only
        - currency: ISO code (default "NGN")
        - transaction_date: YYYY-MM-DD
        - transaction_time: 24-hour HH:MM
        - sender/recipient: name, account (may be masked), bank
        - narration, reference, session_id (NIP session ID)
        INSTRUCTIONS;
    }

    // Define the JSON schema for structured output
    // This ensures the AI returns validated, typed data matching our expected structure
    public function schema(JsonSchema $schema): array
    {
        return [
            // Core transaction identification - normalized categories
            'transaction_type' => $schema->string()->required()->description('Normalized type: outward_transfer, inward_transfer, internal_transfer, airtime, bill_payment, pos, withdrawal, deposit'),
            'status' => $schema->string()->required()->description('Normalized: successful, pending, or failed'),
            'amount' => $schema->number()->required()->description('Transaction amount'),
            'fee' => $schema->number()->description('Transfer fee / charges, if shown'),
            'stamp_duty' => $schema->number()->description('Electronic levy / stamp duty, if shown'),
            'currency' => $schema->string()->default('NGN')->description('ISO currency code'),
            'transaction_date' => $schema->string()->required()->description('ISO date YYYY-MM-DD'),
            'transaction_time' => $schema->string()->description('24-hour time if visible'),

            // Sender (debiting party) details
            'sender_name' => $schema->string()->description('Name of the sender / debited account'),
            'sender_account' => $schema->string()->description('Sender account number (may be masked)'),
            'sender_bank' => $schema->string()->description('Sender bank name'),

            // Recipient (crediting party) details
            'recipient_name' => $schema->string()->description('Name of the recipient / credited account'),
            'recipient_account' => $schema->string()->description('Recipient account number (may be masked)'),
            'recipient_bank' => $schema->string()->description('Recipient bank name'),

            // Additional transaction metadata
            'narration' => $schema->string()->description('Remark / description / purpose'),
            'reference' => $schema->string()->description('Transaction reference or ID'),
            'session_id' => $schema->string()->description('NIP session ID (the long numeric string), if shown'),
        ];
    }
}
