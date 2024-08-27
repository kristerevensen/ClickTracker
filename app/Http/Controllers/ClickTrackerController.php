<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CampaignLink;
use App\Models\CampaignLinkClick;
use App\Models\ErrorLog;
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
            $this->logError('Invalid link token provided.', $linkToken, $request);
            return redirect()->route('invalid.link');
        }

        // Retrieve the campaign link using the linkToken
        $campaignLink = CampaignLink::where('link_token', $linkToken)->first();

        if (!$campaignLink) {
            $this->logError('Campaign link not found.', $linkToken, $request);
            return redirect()->route('invalid.link');
        }

        // Retrieve the associated campaign
        $campaign = $campaignLink->campaign;

        // Check if the campaign is active
        if (!$campaign->status) {
            $this->logError('Campaign is inactive.', $linkToken, $request);
            return redirect()->route('invalid.link');
        }

        // Check if the campaign has an end date and if it has passed
        if ($campaign->end && now()->greaterThan($campaign->end)) {
            $this->logError('Campaign has expired.', $linkToken, $request);
            return redirect()->route('invalid.link');
        }

        // Use DeviceDetector to parse the user agent
        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_NONE);

        $dd = new DeviceDetector($request->userAgent());
        $dd->parse();

        $browser = $dd->getClient();
        $os = $dd->getOs();
        $device = $dd->getDeviceName();

        // Store the click data
        $click = new CampaignLinkClick();
        $click->user_agent = $request->userAgent();
        $click->referrer = $request->headers->get('referer');
        $click->ip = $request->ip();
        $click->platform = $os['name'] ?? null;
        $click->browser = $browser['name'] ?? null;
        $click->device_type = $device;
        $click->screen_resolution = $request->headers->get('screen-resolution');
        $click->language = $request->headers->get('accept-language');
        $click->session_id = $request->session()->getId();
        $click->link_token = $linkToken;

        // Save the click and redirect to the landing page if successful
        if ($click->save()) {
            $landingPage = $campaignLink->landing_page;
            $queryParams = $request->query(); // Existing query parameters

            // If UTM is activated, prepare UTM parameters
            $utmParams = [];
            if ($campaign->utm_activated) {
                if (!empty($campaign->campaign_name)) {
                    $utmParams['utm_campaign'] = $campaign->force_lowercase
                        ? str_replace(' ', '_', strtolower($campaign->campaign_name))
                        : str_replace(' ', '_', $campaign->campaign_name);
                }

                if (!empty($campaignLink->source)) {
                    $utmParams['utm_source'] = $campaign->force_lowercase
                        ? str_replace(' ', '_', strtolower($campaignLink->source))
                        : str_replace(' ', '_', $campaignLink->source);
                }

                if (!empty($campaignLink->medium)) {
                    $utmParams['utm_medium'] = $campaign->force_lowercase
                        ? str_replace(' ', '_', strtolower($campaignLink->medium))
                        : str_replace(' ', '_', $campaignLink->medium);
                }

                if (!empty($campaignLink->content)) {
                    $utmParams['utm_content'] = $campaign->force_lowercase
                        ? str_replace(' ', '_', strtolower($campaignLink->content))
                        : str_replace(' ', '_', $campaignLink->content);
                }
            }

            // Handle custom parameters with additional whitespace removal
            $customParams = [];
            if (!empty($campaignLink->custom_parameters)) {
                $customParamsArray = explode(',', $campaignLink->custom_parameters);
                foreach ($customParamsArray as $param) {
                    $keyValue = explode('=', $param);
                    if (count($keyValue) === 2) {
                        $key = trim(str_replace(' ', '', $keyValue[0])); // Remove spaces from key
                        $value = trim(str_replace(' ', '', $keyValue[1])); // Remove spaces from value
                        $customParams[$key] = $campaign->force_lowercase
                            ? strtolower($value)
                            : $value;
                    }
                }
            }

            // Merge existing query parameters, UTM parameters, and custom parameters
            $allParams = array_merge($queryParams, $utmParams, $customParams);

            // Parse the landing page URL
            $parsedUrl = parse_url($landingPage);

            // Handle the query string, preserving existing ones and appending new ones
            $existingQueryString = isset($parsedUrl['query']) ? $parsedUrl['query'] : '';
            $newQueryString = http_build_query($allParams);

            // Combine existing and new query strings
            $finalQueryString = trim($existingQueryString . '&' . $newQueryString, '&');

            // Reconstruct the landing page URL with proper query parameters
            $landingPageWithParams = (isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '')
                . (isset($parsedUrl['host']) ? $parsedUrl['host'] : '')
                . (isset($parsedUrl['path']) ? $parsedUrl['path'] : '')
                . (!empty($finalQueryString) ? '?' . $finalQueryString : '')
                . (isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '');

            return redirect()->away($landingPageWithParams);
        }

        $this->logError('Failed to save campaign link click.', $linkToken, $request);
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
        $stackTrace = json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        ErrorLog::create([
            'link_token' => $linkToken,
            'error_message' => $errorMessage,
            'stack_trace' => $stackTrace,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
