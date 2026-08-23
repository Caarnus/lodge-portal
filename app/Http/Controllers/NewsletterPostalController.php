<?php

namespace App\Http\Controllers;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\DeliveryChannel;
use App\Models\Lodge;
use App\Models\NewsletterIssue;
use App\Services\Audit;
use App\Services\CommunicationDistributionService;
use Illuminate\Http\Request;

class NewsletterPostalController extends Controller
{
    public function export(Request $request, Lodge $lodge, NewsletterIssue $issue, CommunicationDistributionService $distributions)
    {
        $this->allow($lodge, $issue);
        $version = $issue->published()->firstOrFail();
        $run = $version->distributionRuns()->where('kind', 'newsletter')->where('postal_recipient_count', '>', 0)->latest()->first() ?? $distributions->newsletter($lodge, $version, $request->user(), false, true);
        $deliveries = $run->deliveries()->where('channel', DeliveryChannel::Postal)->whereIn('status', [CommunicationDeliveryStatus::Pending, CommunicationDeliveryStatus::Prepared])->get();
        $deliveries->where('status', CommunicationDeliveryStatus::Pending)->each(fn ($delivery) => $delivery->update(['status' => CommunicationDeliveryStatus::Prepared, 'prepared_at' => now()]));
        Audit::record('newsletter.postal_exported', $run, $lodge, null, ['id' => $run->id, 'count' => $deliveries->count()]);
        $callback = function () use ($deliveries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Delivery ID', 'Name', 'Address line 1', 'Address line 2', 'City', 'State', 'Postal code']);
            foreach ($deliveries as $d) {
                fputcsv($out, [$d->id, $this->safe($d->recipient_name), $this->safe($d->mailing_address_line_1), $this->safe($d->mailing_address_line_2), $this->safe($d->mailing_city), $this->safe($d->mailing_state), $this->safe($d->mailing_postal_code)]);
            } fclose($out);
        };

        return response()->streamDownload($callback, 'newsletter-postal.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function mailed(Request $request, Lodge $lodge, NewsletterIssue $issue)
    {
        $this->allow($lodge, $issue);
        $ids = $request->validate(['delivery_ids' => 'required|array', 'delivery_ids.*' => 'integer'])['delivery_ids'];
        $version = $issue->published()->firstOrFail();
        $count = $version->distributionRuns()->where('kind', 'newsletter')->get()->flatMap->deliveries()->where('channel', DeliveryChannel::Postal)->whereIn('id', $ids)->where('status', CommunicationDeliveryStatus::Prepared)->each(fn ($delivery) => $delivery->update(['status' => CommunicationDeliveryStatus::Mailed, 'mailed_at' => now()]))->count();
        Audit::record('newsletter.postal_mailed', $issue, $lodge, null, ['count' => $count]);

        return back();
    }

    public function print(Lodge $lodge, NewsletterIssue $issue)
    {
        $this->allow($lodge, $issue);
        $version = $issue->published()->with('document')->firstOrFail();
        if ($version->document) {
            return redirect()->route('lodges.newsletters.document', [$lodge, $issue]);
        }

return response(view('newsletters.print', compact('lodge', 'issue', 'version')), 200, ['Cache-Control' => 'private, no-store']);
    }

    private function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function allow(Lodge $lodge, NewsletterIssue $issue): void
    {
        abort_unless($issue->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'communications.recipients');
    }
}
