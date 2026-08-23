<?php

namespace App\Services;

use App\Models\Lodge;

class EffectiveLodgeCommunicationSettings
{
    public function for(Lodge $lodge): array
    {
        $settings = $lodge->communicationSetting;

        return [
            'sender_display_name' => $settings?->sender_display_name ?: $lodge->name,
            'reply_to_email' => $settings?->reply_to_email ?: $lodge->public_email,
            'secretary_email' => $settings?->secretary_email ?: $lodge->public_email,
            'newsletter_contact_email' => $settings?->newsletter_contact_email ?: $settings?->secretary_email ?: $lodge->public_email,
        ];
    }
}
