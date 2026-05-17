<?php

namespace App\Jobs;

use App\Mail\PromotionCreated;
use App\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPromotionEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $promotion;

    public function __construct(UserModel $user, array $promotion)
    {
        $this->user = $user;
        $this->promotion = $promotion;
    }

    public function handle(): void
    {
        \Log::info("Sending promotion email for '{$this->promotion['name']}' to: {$this->user->email}");
        try {
            Mail::to($this->user->email)->send(new PromotionCreated($this->promotion));
            \Log::info("Promotion email sent successfully to: {$this->user->email}");
        } catch (\Exception $e) {
            \Log::error("Failed to send promotion email to {$this->user->email}: " . $e->getMessage());
            throw $e;
        }
    }
}
