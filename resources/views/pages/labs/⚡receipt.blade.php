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

<div class="mx-auto w-full max-w-3xl space-y-6">
    {{-- Shared page intro, matching the other labs. --}}
    <x-lab-header title="Transaction receipt validator"
        description="Upload a photo of a bank receipt or invoice and see the transaction fields the AI finds." />

    {{-- Main card container --}}
    <flux:card class="space-y-4 border-line! bg-card!">
        {{-- Upload section header --}}
        <div class="space-y-3">
            <flux:heading size="lg" class="font-display">Upload transaction receipt</flux:heading>
            <p class="text-sm text-muted">Upload a bank transaction receipt (transfer, airtime, bill payment, POS,
                withdrawal). JPG or PNG, max 10MB.</p>
        </div>

        {{-- Show preview + actions when image is uploaded --}}
        @if ($receiptImage)
            <div class="space-y-3">
                {{-- Image preview box with loading state --}}
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-lg border border-line bg-paper dark:bg-white/5">
                    {{-- wire:loading.attr="hidden" hides image while loading --}}
                    <img wire:loading.attr="hidden" src="{{ $receiptImage->temporaryUrl() }}" alt="Receipt preview"
                        class="h-full w-full object-cover" />
                </div>

                {{-- Action buttons: Validate (primary green) and Clear (ghost) --}}
                <div class="flex gap-3">
                    <flux:button wire:click="processReceipt" :disabled="$processing" variant="primary" class="flex-1">
                        {{-- Flux ships an animated loading icon, so no custom SVG is needed. --}}
                        @if ($processing)
                            <flux:icon.arrow-path variant="micro" class="animate-spin" />
                            Validating&hellip;
                        @else
                            Validate transaction
                        @endif
                    </flux:button>

                    <flux:button wire:click="clearReceipt" variant="ghost" :disabled="$processing">
                        Clear
                    </flux:button>
                </div>
            </div>
        @else
            {{-- Dropzone for drag & drop / click to upload. --}}
            {{-- wire:dropzone binds file drops to $receiptImage property. --}}
            <div wire:dropzone="receiptImage"
                class="relative cursor-pointer rounded-lg border-2 border-dashed border-line p-8 text-center transition-colors hover:border-brand-400 hover:bg-brand-50/40 dark:hover:border-brand-600 dark:hover:bg-brand-950/30">
                <flux:icon name="cloud-arrow-up" class="mx-auto h-12 w-12 text-brand-600 dark:text-brand-400" />
                <p class="mt-3 text-sm text-muted">
                    Drag &amp; drop a receipt image, or click to browse
                </p>
                {{-- Hidden file input bound to the Livewire property. --}}
                <input type="file" wire:model="receiptImage" accept="image/*" class="hidden" id="receipt-upload" />
                {{-- Label makes the entire dropzone clickable. --}}
                <label for="receipt-upload" class="absolute inset-0 cursor-pointer" />
            </div>
        @endif

        {{-- Error display card (red) --}}
        @if ($error)
            <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <flux:icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-red-500" />
                <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
            </div>
        @endif

        {{-- Results display section --}}
        @if ($result)
            <div class="space-y-4 border-t border-line pt-4">
                {{-- Header with title and Copy JSON button --}}
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" class="font-display">Validation result</flux:heading>
                    <flux:button type="button" variant="ghost" size="sm" icon="clipboard"
                        x-on:click="navigator.clipboard.writeText(JSON.stringify(@js($result), null, 2))">
                        Copy JSON
                    </flux:button>
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
                        <flux:text class="text-xs text-muted">Amount</flux:text>
                        <p class="text-lg font-bold text-ink dark:text-white">
                            {{ number_format($result['amount'] ?? 0, 2) }} {{ $result['currency'] ?? 'NGN' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-muted">Fee</flux:text>
                        <p class="font-medium">
                            {{ isset($result['fee']) ? number_format($result['fee'], 2) . ' ' . ($result['currency'] ?? 'NGN') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-muted">Stamp duty</flux:text>
                        <p class="font-medium">
                            {{ isset($result['stamp_duty']) ? number_format($result['stamp_duty'], 2) . ' ' . ($result['currency'] ?? 'NGN') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-muted">Date</flux:text>
                        <p class="font-medium">{{ $result['transaction_date'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-muted">Time</flux:text>
                        <p class="font-medium">{{ $result['transaction_time'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <flux:text class="text-xs text-muted">Currency</flux:text>
                        <p class="font-medium">{{ $result['currency'] ?? 'NGN' }}</p>
                    </div>
                </div>

                {{-- Sender (debiting party) section - muted paper background --}}
                @if (!empty($result['sender_name']) || !empty($result['sender_account']) || !empty($result['sender_bank']))
                    <div class="rounded-lg bg-paper p-4 dark:bg-white/5">
                        <flux:heading size="sm" class="text-ink/75 dark:text-white/80">Sender (debited)</flux:heading>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <div>
                                <flux:text class="text-xs text-muted">Name</flux:text>
                                <p class="font-medium">{{ $result['sender_name'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-muted">Account</flux:text>
                                <p class="font-mono text-sm font-medium">{{ $result['sender_account'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-muted">Bank</flux:text>
                                <p class="font-medium">{{ $result['sender_bank'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Recipient (crediting party) section - green brand tint --}}
                @if (!empty($result['recipient_name']) || !empty($result['recipient_account']) || !empty($result['recipient_bank']))
                    <div class="rounded-lg bg-brand-50 p-4 dark:bg-brand-950/40">
                        <flux:heading size="sm" class="text-brand-800 dark:text-brand-200">Recipient (credited)</flux:heading>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <div>
                                <flux:text class="text-xs text-muted">Name</flux:text>
                                <p class="font-medium">{{ $result['recipient_name'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-muted">Account</flux:text>
                                <p class="font-mono text-sm font-medium">{{ $result['recipient_account'] ?? 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <flux:text class="text-xs text-muted">Bank</flux:text>
                                <p class="font-medium">{{ $result['recipient_bank'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Additional metadata: narration, reference, NIP session ID --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    @if (!empty($result['narration']))
                        <div class="col-span-2">
                            <flux:text class="text-xs text-muted">Narration</flux:text>
                            <p class="font-medium">{{ $result['narration'] }}</p>
                        </div>
                    @endif
                    @if (!empty($result['reference']))
                        <div>
                            <flux:text class="text-xs text-muted">Reference</flux:text>
                            <p class="font-mono text-sm font-medium">{{ $result['reference'] }}</p>
                        </div>
                    @endif
                    @if (!empty($result['session_id']))
                        <div>
                            <flux:text class="text-xs text-muted">NIP session ID</flux:text>
                            <p class="font-mono text-sm font-medium">{{ $result['session_id'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </flux:card>

    {{-- Info card listing supported receipt types --}}
    <flux:card class="border-line! bg-card!">
        <flux:heading size="sm" class="font-display">Supported receipt types</flux:heading>
        <ul class="mt-3 list-inside list-disc space-y-2 text-sm text-muted">
            <li>Bank transfers (inter-bank, intra-bank, NIP)</li>
            <li>Airtime &amp; data purchases</li>
            <li>Bill payments (electricity, TV, internet, etc.)</li>
            <li>POS transactions</li>
            <li>ATM withdrawals &amp; deposits</li>
            <li>USSD &amp; mobile banking receipts</li>
        </ul>
        <p class="mt-3 text-xs text-muted">Optimized for Nigerian banking receipts (GTBank, Access, Zenith, UBA,
            FirstBank, Opay, Palmpay, Kuda, etc.)</p>
    </flux:card>
</div>
