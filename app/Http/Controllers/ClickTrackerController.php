<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CampaignLink;
use App\Models\CampaignLinkClick;
use App\Models\ErrorLog; // Import the ErrorLog model
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

class ClickTrackerController extends Controller
{
    public function index(Request $request)
    {
        // Retrieve the linkToken from the URL
        $linkToken = $request->route('linkToken');

        // Check if linkToken is provided and valid
        if (empty($linkToken)) {
            // Log the error to the database
            $this->logError('Invalid link token provided.', $linkToken, $request);

            // Redirect to a page saying the link is invalid
            return redirect()->route('invalid.link');
        }

        // Retrieve the campaign link using the linkToken
        $campaignLink = CampaignLink::where('link_token', $linkToken)->first();

        if (!$campaignLink) {
            // Log the error to the database
            $this->logError('Campaign link not found.', $linkToken, $request);

            // Redirect to a page saying the link is invalid
            return redirect()->route('invalid.link');
        }

        // Use DeviceDetector to parse the user agent
        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_NONE);

        $dd = new DeviceDetector($request->userAgent());
        $dd->parse();

        $browser = $dd->getClient(); // Returns an array with 'name', 'version'
        $os = $dd->getOs(); // Returns an array with 'name', 'version'
        $device = $dd->getDeviceName(); // Device type: desktop, smartphone, etc.

        // Store the click data
        $click = new CampaignLinkClick();
        $click->user_agent = $request->userAgent();
        $click->referrer = $request->headers->get('referer');
        $click->ip = $request->ip();
        $click->platform = $os['name'] ?? null;
        $click->browser = $browser['name'] ?? null;
        $click->device_type = $device;
        $click->screen_resolution = $request->headers->get('screen-resolution'); // Assume client sends this
        $click->language = $request->headers->get('accept-language');
        $click->session_id = $request->session()->getId();
        $click->link_token = $linkToken;

        // Save the click and redirect to the landing page if successful
        if ($click->save()) {

            // Extract the parameters from the request URL
            $queryParams = $request->query();
            $landingPage = $campaignLink->landing_page;

            // Build UTM parameters
            $utmParams = [
                'utm_campaign' => str_replace(' ', '_', strtolower($campaignLink->campaign->name)),
                'utm_source' => str_replace(' ', '_', strtolower($campaignLink->source)),
                'utm_medium' => str_replace(' ', '_', strtolower($campaignLink->medium)),
                'utm_content' => str_replace(' ', '_', strtolower($campaignLink->content)),
            ];

            // Merge UTM parameters with existing query parameters
            $allParams = array_merge($queryParams, $utmParams);

            // Parse the landing page URL
            $parsedUrl = parse_url($landingPage);

            // Build the query string for the landing page
            $queryString = http_build_query($allParams);

            // Reconstruct the landing page URL with the new query string
            $landingPageWithParams = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
            $landingPageWithParams .= isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
            $landingPageWithParams .= isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
            $landingPageWithParams .= !empty($queryString) ? '?' . $queryString : '';
            $landingPageWithParams .= isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

            return redirect()->away($landingPageWithParams);
        }

        // Log the error to the database if click save fails
        $this->logError('Failed to save campaign link click.', $linkToken, $request);

        // Redirect to an error page
        return redirect()->route('error.page')->with('message', 'Failed to process the click. Please try again later.');
    }

    /**
     * Log an error to the database.
     *
     * @param string $errorMessage
     * @param string|null $linkToken
     * @param Request $request
     */
    private function logError(string $errorMessage, ?string $linkToken, Request $request): void
    {
        // Serialize the stack trace as a JSON string
        $stackTrace = json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        // Create a new error log entry
        ErrorLog::create([
            'link_token' => $linkToken,
            'error_message' => $errorMessage,
            'stack_trace' => $stackTrace,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
