<?php

namespace App\Jobs;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\DistributionRunStatus;
use App\Enums\LodgeCommunicationStatus;
use App\Models\CommunicationDelivery;
use App\Services\CommunicationDistributionService;
use App\Services\EffectiveLodgeCommunicationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendCommunicationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $deliveryId)
    {
    }

    public function handle(CommunicationDistributionService $service, EffectiveLodgeCommunicationSettings $settings): void
    {
        $delivery = CommunicationDelivery::query()->with(['run.newsletterIssueVersion.document', 'run.newsletterIssueVersion.issue', 'run.lodgeCommunication', 'run.lodge'])->find($this->deliveryId);
        if (!$delivery || $delivery->status !== CommunicationDeliveryStatus::Claimed) {
            return;
        }
        if (!$service->canSend($delivery)) {
            $delivery->update(['status' => CommunicationDeliveryStatus::Skipped, 'skip_reason' => 'current_eligibility_or_consent', 'attempted_at' => now()]);

            return;
        }
        $lodgeSettings = $settings->for($delivery->run->lodge);
        $unsubscribe = Str::random(48);
        $delivery->update(['unsubscribe_token_hash' => hash('sha256', $unsubscribe)]);
        try {
            $subject = $delivery->run->kind === 'newsletter' ? $delivery->run->newsletterIssueVersion->title : $delivery->run->lodgeCommunication->subject;
            $body = $delivery->run->kind === 'newsletter' ? ($delivery->run->newsletterIssueVersion->body_html ?: '<p>Please find your lodge newsletter attached.</p>') : $delivery->run->lodgeCommunication->body_html;
            $unsubscribeUrl = route('public.communications.unsubscribe.show', [$delivery->run->lodge, $unsubscribe]);
            Mail::html($body . '<p><small>' . $delivery->run->lodge->name . ' · <a href="' . $unsubscribeUrl . '">Unsubscribe from lodge email</a></small></p>', function ($mail) use ($delivery, $subject, $lodgeSettings) {
                $mail->to($delivery->recipient_email)->subject($subject)->replyTo($lodgeSettings['reply_to_email'], $lodgeSettings['sender_display_name']);
                if ($delivery->run->kind === 'newsletter' && $document = $delivery->run->newsletterIssueVersion->document) {
                    if (Storage::disk('local')->exists($document->storage_path)) {
                        $mail->attach(Storage::disk('local')->path($document->storage_path), ['as' => $document->original_name, 'mime' => 'application/pdf']);
                    }
                }
            });
            $delivery->update(['status' => CommunicationDeliveryStatus::Sent, 'sent_at' => now(), 'attempted_at' => now()]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => CommunicationDeliveryStatus::Failed, 'attempted_at' => now(), 'last_error' => str($exception->getMessage())->limit(1000)]);
        }
        $this->reconcile($delivery->run->fresh());
    }

    private function reconcile($run): void
    {
        $failed = $run->deliveries()->where('status', CommunicationDeliveryStatus::Failed)->exists();
        $open = $run->deliveries()->whereIn('status', [CommunicationDeliveryStatus::Pending, CommunicationDeliveryStatus::Claimed])->exists();
        if (!$open) {
            $run->update(['status' => $failed ? DistributionRunStatus::CompletedWithFailures : DistributionRunStatus::Completed, 'sent_count' => $run->deliveries()->where('status', CommunicationDeliveryStatus::Sent)->count(), 'failed_count' => $run->deliveries()->where('status', CommunicationDeliveryStatus::Failed)->count(), 'skipped_count' => $run->deliveries()->where('status', CommunicationDeliveryStatus::Skipped)->count()]);
            if ($run->lodge_communication_id) {
                $run->lodgeCommunication()->update(['status' => LodgeCommunicationStatus::Sent, 'sent_at' => now()]);
            }
        }
    }
}
