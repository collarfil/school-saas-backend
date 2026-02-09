<?php

namespace App\Listeners;

use App\Events\SubscriptionActivated;
use App\Mail\SubscriptionActivatedMail;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionActivatedNotification
{
    public function handle(SubscriptionActivated $event): void
    {
        $subscription = $event->subscription;
        $school = $subscription->school;
        $admin = $school->admin;

        if ($admin) {
            Mail::to($admin->email)->send(new SubscriptionActivatedMail($subscription));
        }
    }
}