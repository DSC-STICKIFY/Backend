<?php
namespace App\Jobs;

use App\Mail\PromotionMail;
use App\Models\Promotion;
use App\Models\PromotionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPromotionChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Promotion $promotion,
        public int $logId,
        public array $emails
    ) {}

    public function handle(): void
    {
        $log = PromotionLog::find($this->logId);
        $success = 0;
        $failed  = 0;
        $failedEmails = [];

        foreach ($this->emails as $email) {
            try {
                Mail::to($email)->send(new PromotionMail($this->promotion));
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $failedEmails[] = $email;
            }
        }

        // Update the log atomically
        if ($log) {
            $log->increment('successful_sends', $success);
            $log->increment('failed_sends', $failed);

            if (!empty($failedEmails)) {
                $existing = $log->failed_emails ?? [];
                $log->update([
                    'failed_emails' => array_merge($existing, $failedEmails),
                ]);
            }
        }
    }
}
