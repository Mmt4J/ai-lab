<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use App\Ai\Agents\ReceiptReader;
use Laravel\Ai\Files\Image;

// Anonymous Livewire component class for the receipt validation page
new class extends Component {
    // Trait that enables file upload handling in Livewire
    use WithFileUploads; // 10MB max

    #[Validate('required|image|max:10240')]
    public $receiptImage = null;

    // Stores the structured AI response after processing
    public array $result = [];
    // Loading state flag for UI feedback during AI processing
    public bool $processing = false;
    // Error message display for user-facing error handling
    public string $error = '';

    // Main handler: validates upload, sends to AI, stores result
    public function processReceipt(): void
    {
        // Run validation rules on the uploaded file
        $this->validate();
        // Set loading state and clear previous results/errors
        $this->processing = true;
        $this->error = '';
        $this->result = [];

        try {
            // Instantiate the ReceiptReader AI agent
            $agent = new ReceiptReader();
            // Send image to AI with prompt - Image::fromUpload handles temp file upload to provider
            $response = $agent->prompt('Extract all transaction data from this bank receipt image. Return structured JSON only.', attachments: [Image::fromUpload($this->receiptImage)]);

            // Convert structured response to array for Blade template access
            $this->result = $response->toArray();
        } catch (\Laravel\Ai\Exceptions\InsufficientCreditsException $e) {
            // Handle provider quota/credit exhaustion
            $this->error = 'AI service has insufficient credits. Please check your provider account.';
        } catch (\Laravel\Ai\Exceptions\RateLimitedException $e) {
            // Handle provider rate limiting
            $this->error = 'AI service is rate limited. Please try again in a moment.';
        } catch (\Throwable $e) {
            // Catch-all for unexpected errors with logging
            $this->error = 'Failed to process receipt: ' . $e->getMessage();
            \Log::error('ReceiptReader error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        } finally {
            // Always clear loading state
            $this->processing = false;
        }
    }

    // Reset component state to initial values
    public function clearReceipt(): void
    {
        $this->reset(['receiptImage', 'result', 'error']);
    }
};
?>

<div class="max-w-3xl space-y-6">
    {{-- Page header --}}
    <flux:heading size="xl">Transaction Receipt Validator</flux:heading>

    {{-- Main card container --}}
    <flux:card class="space-y-4">
        {{-- Upload section header --}}
        <div class="space-y-3">
            <flux:heading size="lg">Upload Transaction Receipt</flux:heading>
            <p class="text-sm text-zinc-500">Upload a bank transaction receipt (transfer, airtime, bill payment, POS,
                withdrawal). JPG, PNG, max 10MB</p>
        </div>

        {{-- Show preview + actions when image is uploaded --}}
        @if ($receiptImage)
            <div class="space-y-3">
                {{-- Image preview box with loading state --}}
                <div
                    class="relative aspect-[4/3] rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
                    {{-- wire:loading.attr="hidden" hides image while loading --}}
                    <img wire:loading.attr="hidden" src="{{ $receiptImage->temporaryUrl() }}" alt="Receipt preview"
                        class="w-full h-full object-cover" />
                </div>

                {{-- Action buttons: Validate (primary) and Clear (ghost) --}}
                <div class="flex gap-3">
                    <flux:button wire:click="processReceipt" :disabled="$processing" variant="primary" class="flex-1">
                        {{-- Show spinner + "Validating..." when processing --}}
                        @if ($processing)
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Validating...
                        @else
                            Validate Transaction
                        @endif
                    </flux:button>

                    <flux:button wire:click="clearReceipt" variant="ghost" :disabled="$processing">
                        Clear
                    </flux:button>
                </div>
            </div>
        @else
            {{-- Dropzone for drag & drop / click to upload --}}
            {{-- wire:dropzone binds file drops to $receiptImage property --}}
            <div wire:dropzone="receiptImage"
                class="relative border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg p-8 text-center hover:border-primary-500 transition-colors cursor-pointer">
                <flux:icon name="cloud-arrow-up" class="mx-auto h-12 w-12 text-zinc-400" />
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    Drag & drop a receipt image, or click to browse
                </p>
                {{-- Hidden file input bound to Livewire property --}}
                <input type="file" wire:model="receiptImage" accept="image/*" class="hidden" id="receipt-upload" />
                {{-- Label makes entire dropzone clickable to trigger file input --}}
                <label for="receipt-upload" class="absolute inset-0 cursor-pointer" />
            </div>
        @endif

        {{-- Error display card (red) --}}
        @if ($error)
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <flux:icon name="exclamation-triangle" class="h-5 w-5 text-red-500" />
                <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
            </div>
        @endif

        {{-- Results display section --}}
        @if ($result)
            <div class="space-y-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                {{-- Header with title and Copy JSON button --}}
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Validation Result</flux:heading>
                    <button type="button"
                        @click="navigator.clipboard.writeText(JSON.stringify(@json($result), null, 2))"
                        class="text-xs border border-zinc-300 dark:border-zinc-600 rounded-md px-3 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        Copy JSON
                    </button>
                </div>

                {{-- Status badge with color coding --}}
                @if (isset($result['status']))
                    @php
                        $statusVariant = match (strtolower($result['status'])) {
                            'successful' => 'success', // Green
                            'pending' => 'warning', // Yellow
                            default => 'danger', // Red
                        };
                    @endphp

                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <flux:badge :variant="$statusVariant" size="lg" class="shrink-0">
                            {{ $result['status'] }}
                        </flux:badge>

                        @if (isset($result['transaction_type']))
                            <flux:badge variant="outline" size="lg"
                                class="min-w-0 max-w-full whitespace-normal! text-left break-words">
                                {{ $result['transaction_type'] }}
                            </flux:badge>
                        @endif
                    </div>
                @endif
                {{-- Core transaction details grid --}}
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Amount</flux:text>
                        <p class="font-bold text-lg text-zinc-900 dark:text-zinc-100">
                            {{ number_format($result['amount'] ?? 0, 2) }} {{ $result['currency'] ?? 'NGN' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Fee</flux:text>
                        <p class="font-medium">
                            {{ isset($result['fee']) ? number_format($result['fee'], 2) . ' ' . ($result['currency'] ?? 'NGN') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Stamp Duty</flux:text>
                        <p class="font-medium">
                            {{ isset($result['stamp_duty']) ? number_format($result['stamp_duty'], 2) . ' ' . ($result['currency'] ?? 'NGN') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Date</flux:text>
                        <p class="font-medium">{{ $result['transaction_date'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Time</flux:text>
                        <p class="font-medium">{{ $result['transaction_time'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Currency</flux:text>
                        <p class="font-medium">{{ $result['currency'] ?? 'NGN' }}</p>
                    </div>
                </div>

                {{-- Sender (debiting party) section - grey background --}}
                @if (!empty($result['sender_name']) || !empty($result['sender_account']) || !empty($result['sender_bank']))
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">Sender (Debited)
                        </flux:heading>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Name</flux:text>
                                <p class="font-medium">{{ $result['sender_name'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Account</flux:text>
                                <p class="font-medium font-mono text-sm">{{ $result['sender_account'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Bank</flux:text>
                                <p class="font-medium">{{ $result['sender_bank'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Recipient (crediting party) section - green background --}}
                @if (!empty($result['recipient_name']) || !empty($result['recipient_account']) || !empty($result['recipient_bank']))
                    <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <flux:heading size="sm" class="text-green-700 dark:text-green-300">Recipient (Credited)
                        </flux:heading>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Name</flux:text>
                                <p class="font-medium">{{ $result['recipient_name'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Account</flux:text>
                                <p class="font-medium font-mono text-sm">{{ $result['recipient_account'] ?? 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Bank</flux:text>
                                <p class="font-medium">{{ $result['recipient_bank'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Additional metadata: narration, reference, NIP session ID --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    @if (!empty($result['narration']))
                        <div class="col-span-2">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Narration</flux:text>
                            <p class="font-medium">{{ $result['narration'] }}</p>
                        </div>
                    @endif
                    @if (!empty($result['reference']))
                        <div>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Reference</flux:text>
                            <p class="font-medium font-mono text-sm">{{ $result['reference'] }}</p>
                        </div>
                    @endif
                    @if (!empty($result['session_id']))
                        <div>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">NIP Session ID</flux:text>
                            <p class="font-medium font-mono text-sm">{{ $result['session_id'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </flux:card>

    {{-- Info card listing supported receipt types --}}
    <flux:card>
        <flux:heading size="sm">Supported Receipt Types</flux:heading>
        <ul class="mt-3 text-sm text-zinc-600 dark:text-zinc-400 space-y-2 list-disc list-inside">
            <li>Bank transfers (inter-bank, intra-bank, NIP)</li>
            <li>Airtime & data purchases</li>
            <li>Bill payments (electricity, TV, internet, etc.)</li>
            <li>POS transactions</li>
            <li>ATM withdrawals & deposits</li>
            <li>USSD & mobile banking receipts</li>
        </ul>
        <p class="mt-3 text-xs text-zinc-500">Optimized for Nigerian banking receipts (GTBank, Access, Zenith, UBA,
            FirstBank, Opay, Palmpay, Kuda, etc.)</p>
    </flux:card>
</div>
