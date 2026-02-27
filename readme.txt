=== Simple Web Marketing Attribution ===
Contributors: simplewebmarketing
Tags: attribution, marketing, utm, referrer, tracking
Requires at least: 4.7
Tested up to: 6.9
Stable tag: 1.2.3
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to capture and store web marketing attribution tags with most forms.

== Description ==

The Simple Web Marketing Attribution plugin captures attribution data from users visiting your site, including UTM parameters, referrer information, and automatic detection of webmail providers (Gmail, Yahoo, etc.) and search engines. It stores this data in the visitor's browser local storage and automatically submits it to hidden fields in forms. Capturing these attribution utm parameters with your lead records will let you run reports on your customer database to see which marketing channels result in customers and which do not. 

The plugin also includes a link builder tool to help you generate UTM tags for your marketing campaigns and a link library to keep track of the UTM tags you have generated.

The plugin is tested and works with:

*   HubSpot
*   Salesforce (Web-to-Lead)
*   Contact Form 7
*   WPForms
*   Ninja Forms
*   Gravity Forms

The plugin includes:

*   A lightweight JavaScript file to capture url attribution data from the browser.
*   An admin dashboard to view instructions for using the plugin.
*   A UTM tag generator and logging tool.
*   A HubSpot proxy to forward attribution data to your HubSpot account.
*   Resilient AJAX form integration for modern, single-page submission tracking.

== External services ==

This plugin utilizes the HubSpot Forms API to provide advanced attribution tracking for HubSpot forms.

*   **Service:** HubSpot Forms API (provided by HubSpot, Inc.)
*   **Purpose:** To submit form data and associated marketing attribution metadata (UTM parameters, referrer) from your site to your HubSpot portal when the HubSpot Proxy feature is used.
*   **Data Sent:** When a visitor submits a form configured with our tracking, the plugin sends the form field values (user-provided input), the page URL, the page title, and captured attribution data (UTM source, medium, campaign, term, content, and full attribution log).
*   **Terms & Privacy:** This service is subject to HubSpot's [Terms of Service](https://legal.hubspot.com/terms-of-service) and [Privacy Policy](https://legal.hubspot.com/privacy-policy).

== Frequently Asked Questions ==

= How does the plugin work? =

The plugin uses a small JavaScript file to capture UTM parameters from the URL and the referrer from the browser. It intelligently maps known search engines to "organic" and webmail providers to "email" to provide clearer attribution out of the box. This data is then stored in the website visitors browser's local storage and copied into any form they submit that contains hidden text input fields. The hidden fields must have either a name, ID, or class attribute with a value matching the UTM parameter you want to capture.

= Where can I see the attribution data? =

The attribution data is captured with the form submission without the website visitor having to fill it into the form. Keeping this ad source data with the user record makes it visible anywhere you copy or export the user record, helping you identify the online marketing source of your customers.

== Screenshots ==

1. Dashboard with reporting of best performing channels and timeframe selector.
2. Link builder tool for generating UTM tags and a notes field for logging campaign links.
3. Link Library for referincing and reusing previously generated and published campaign links. 
4. Settings allow for preserving or deleting data when uninstalling the plugin.
5. Simple to find and follow instructions.

== Changelog ==

= 1.2.3 =
*   **Admin UI Polish:** Refined page titles and helper text across Link Builder and Link Library for better clarity.
*   **Navigation Fix:** Resolved issue where admin submenus were not correctly highlighted in the WordPress sidebar.
*   **Salesforce Optimization:** Salesforce Web-to-Lead capture is now enabled by default for all users, streamlining the setup process.
*   **Accessibility Improvements:** Increased global font sizes and improved layout contrast for better readability.

= 1.2.2 =
*   **Gravity Forms Compatibility:** Full support for Gravity Forms, including both AJAX and standard postback submission modes.
*   **Proactive Attribution Capture:** Added intelligent detection for form confirmations on page load to ensure 100% conversion tracking accuracy.
*   **Improved Form Detection:** Enhanced script logic to automatically identify and populate Gravity Forms fields without custom configuration.

= 1.2.1 =
*   **Webmail Attribution:** Added automatic detection for Gmail, Outlook, Yahoo, and other major webmail providers, categorizing this traffic as "email" even without UTMs.
*   **Improved Documentation:** Added detailed "Attribution Logic" guides to explain how the plugin prioritizes sources and handles direct traffic from mail apps.

= 1.2.0 =
*   **Salesforce Web-to-Lead Integration:** Added seamless support for Salesforce Web-to-Lead forms with zero-code mapping.
*   **Smart Proximity Mapping:** Intelligent script automatically identifies and populates CRM fields based on preceding text labels.
*   **Modular CRM Features:** New settings toggle to enable/disable Salesforce-specific optimizations and documentation.
*   **Dashboard Enhancements:** Improved Integrations dashboard with a refined "Getting Started" default view and interactive setup guides.
*   **Code Quality:** Full compliance with WordPress Coding Standards (WPCS) and optimized capture script performance.

= 1.1.1 =
*   **Resilient AJAX Form Integration:** Added universal AJAX (Fetch/XHR) interceptor and specific hooks for Contact Form 7, Ninja Forms, and WPForms to ensure 100% attribution capture on modern sites.
*   **Enhanced Privacy Controls:** Refactored privacy settings into user-friendly "Respect marketing cookie consent" and "Respect GPC" toggles with clearer compliance terminology.
*   **Debug Mode:** New toggle to enable/disable detailed console logging for easier troubleshooting.
*   **Reliability Improvements:** Ensured settings are passed as actual booleans to the frontend to prevent race conditions or type mismatches.

= 1.1.0 =
*   **Privacy by Design:** Added initial support for Global Privacy Control (GPC).
*   **Consent Management:** Integrated with 3rd-party cookie banners and added "Strict/Opt-in" and "Default/Opt-out" operating modes.
*   **Automated Cleanup:** LocalStorage data is now immediately wiped if a visitor revokes consent.
*   **Technical Disclosure Helper:** New dashboard section with copy-pasteable text for site Privacy Policies.
*   **Developer Events:** New `swma_consent_updated` Event for custom CMP integrations.

= 1.0.2 =
= 1.0.1 =
* Bumped version to 1.0.1.
* Updated REST API permissions to allow logged-it users to access dashboard and link builder.
* Verified clean uninstallation.
* Final release preparation.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.2 =
* Compliance fixes and security improvements.

= 1.0.1 =
* Release candidate 1.

= 1.0.0 =
* Initial release.