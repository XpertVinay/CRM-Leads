<?php

namespace App\Modules\Subscriptions\Notifications;

use App\Modules\Subscriptions\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly string $eventType,
        private readonly string $title,
        private readonly string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->subscription->loadMissing(['plan', 'coupon', 'organization']);
        $plan = $this->subscription->plan;

        return [
            'event' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'organization_id' => $this->subscription->organization_id,
            'organization_name' => $this->subscription->organization?->name,
            'subscription_id' => $this->subscription->id,
            'razorpay_subscription_id' => $this->subscription->razorpay_subscription_id,
            'plan_code' => $plan?->code,
            'plan_name' => $plan?->name,
            'plan_price_paise' => $this->subscription->base_amount_paise,
            'discount_paise' => $this->subscription->discount_paise,
            'initial_charge_paise' => $this->subscription->initial_charge_paise,
            'is_mandate_only' => $this->subscription->is_mandate_only,
            'coupon_code' => $this->subscription->coupon?->code,
            'status' => $this->subscription->status,
            'action_url' => route('subscription.index'),
        ];
    }
}
