<?php
namespace App\Jobs;

use App\Models\Promotion;
use App\Models\PromotionLog;
use App\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPromotionEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Promotion $promotion,
        public ?int $sentByUserId = null
    ) {}

    public function handle(): void
    {
        // Resolve target recipients based on target_type
        $query = UserModel::whereNotNull('email_verified_at')
            ->where('receive_promotional_emails', true);

        switch ($this->promotion->target_type) {
            case 'recent_buyers':
                $query->whereHas('orders', function ($q) {
                    $q->where('created_at', '>=', now()->subDays(30));
                });
                break;
            case 'inactive_customers':
                $query->whereDoesntHave('orders', function ($q) {
                    $q->where('created_at', '>=', now()->subDays(90));
                });
                break;
            case 'custom_order_customers':
                $query->whereHas('orders', function ($q) {
                    $q->where('is_customized', true);
                });
                break;
            case 'all_verified':
            default:
                // No extra filter needed
                break;
        }

        $recipients = $query->pluck('email', 'user_id')->toArray();
        $totalRecipients = count($recipients);

        if ($totalRecipients === 0) {
            return;
        }

        // Create a log entry
        $log = PromotionLog::create([
            'promotion_id'     => $this->promotion->promotion_id,
            'sent_by'          => $this->sentByUserId,
            'sent_at'          => now(),
            'total_recipients' => $totalRecipients,
        ]);

        // Dispatch chunk jobs (500 emails per chunk)
        $chunks = array_chunk(array_values($recipients), 500);
        foreach ($chunks as $chunk) {
            SendPromotionChunkJob::dispatch($this->promotion, $log->id, $chunk);
        }
    }
}
