=== Maspik – Multi-Layer Spam Protection ===
Contributors: maspik, yonifre
Donate link: https://paypal.me/yonifre
Tags: spam, anti spam, antispam, contact forms, honeypot
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Multi-layer spam protection for forms, comments and WooCommerce. No CAPTCHA, no puzzles — accurate blocking that works out of the box.

== Description ==

Maspik is a complete spam protection platform for WordPress.

Instead of relying on a single detection method, Maspik combines multiple independent protection layers that work together to stop spam before it reaches your inbox.

Protect contact forms, registration forms, comments, WooCommerce, and custom forms using lightweight local validation together with optional cloud intelligence.

Install. Activate. You're protected.

No CAPTCHA. No puzzles. No interruption for real visitors.

= Why Maspik? =

Most anti-spam plugins rely on one detection technique. Some only use CAPTCHAs. Some only check external APIs. Some only filter keywords.

Maspik combines many protection layers into one unified spam detection engine.

Every submission can be analysed using local validation, behavioural analysis, reputation services, and optional cloud intelligence.

The result is stronger protection with fewer false positives and a better experience for legitimate visitors.

= Protection Layers =

Maspik lets you combine multiple independent protection methods:

* Forbidden keywords
* Email patterns
* URL validation
* Phone format validation
* Character limits
* Link limits
* Emoji filtering
* Honeypot protection
* Verification Key
* Direct POST protection
* IP blocklists
* IP reputation
* Proxy and VPN detection
* Country and continent restrictions (Pro)
* Language rules (Pro)
* Maspik Matrix cloud protection with AI analysis

Enable only the protection layers you need. Everything is configurable.

= Built for Performance =

Most protection happens locally inside WordPress without contacting external services.

Only optional features such as Matrix or IP reputation require cloud requests. Only enabled protection layers are executed.

No unnecessary JavaScript. No front-end slowdown. Optimized for high-traffic websites.

= Privacy First =

Most spam detection happens locally. Cloud-based protection is optional, and only the required data is transmitted when it is enabled.

No visitor tracking. GDPR friendly.

= Works Immediately =

Maspik is designed to work immediately after activation. No complicated configuration, no API keys required, no CAPTCHA setup.

Advanced users can fine-tune every protection layer individually.

= Supported Forms =

Maspik protects WordPress core forms together with many popular form builders:

* Elementor Forms
* Elementor Atomic Forms
* Contact Form 7
* Fluent Forms
* Formidable Forms
* Forminator
* Ninja Forms
* JetFormBuilder
* Everest Forms
* Breakdance Forms
* Bricks Builder
* Divi Contact Form
* Hello Plus
* MetForm
* Bit Form
* BuddyPress Registration
* WordPress Comments
* WordPress Registration
* Custom PHP forms (simple developer API)

Pro also supports:

* WPForms
* Gravity Forms
* WooCommerce Registration
* WooCommerce Checkout

= Maspik Matrix =

Maspik Matrix is an optional cloud protection layer built into Maspik. It complements the local protection engine by analysing submissions using additional cloud intelligence.

Matrix may include:

* IP reputation
* Pattern analysis
* Heuristic detection
* Structural analysis
* AI scoring
* Threat intelligence

Local rules always continue working independently.

Every installation includes free Matrix usage (100 checks per month). Pro includes unlimited usage.

= Submission Log =

Understand exactly why spam was blocked. The log shows:

* Block reason
* Triggered protection layer
* Submitted values
* Full detection trace of every layer that ran
* IP address and country
* Date and time
* Page URL

Mark entries as "Not Spam" to continuously improve your configuration, or turn a blocked value into a rule with one click.

Optionally log passed submissions too, to see exactly why something was not caught.

= Statistics =

Monitor your protection over time:

* Total blocked submissions
* Detection trends
* Protection layer activity
* Matrix usage
* Spam history

= API Integrations =

Optional integrations include:

* AbuseIPDB
* ProxyCheck

Enable only the services you want.

= Designed For =

* Business websites
* WooCommerce stores
* Agencies
* Membership websites
* High-traffic websites
* Enterprise WordPress

= Why Site Owners Choose Maspik =

* No CAPTCHA
* Better user experience
* Works immediately
* Lightweight
* Multiple protection layers
* Detailed spam logs
* Powerful statistics
* Extensive customization
* Local-first architecture
* Optional cloud intelligence
* Import and export your configuration

= Pro Features =

Upgrade to Maspik Pro and unlock:

* Unlimited Matrix protection
* Country and continent restrictions
* Language rules
* Premium integrations (WPForms, Gravity Forms, WooCommerce)
* Central Dashboard to manage rules across multiple websites
* Premium support

Learn more: https://wpmaspik.com/

= Spam Block Guarantee =

Spam is constantly evolving. If unwanted submissions are still getting through, we'll help you configure Maspik until they're blocked.

Join the community, share a sample, and we'll help you improve your protection.

= Custom Forms =

Built your own form in PHP? List the fields you want analysed and Maspik tells you whether to accept the submission:

`$fields = [ [ 'type' => 'text', 'field_name' => 'name', 'value' => $_POST['name'] ?? '' ], [ 'type' => 'email', 'field_name' => 'email', 'value' => $_POST['email'] ?? '' ], [ 'type' => 'textarea', 'field_name' => 'message', 'value' => $_POST['message'] ?? '' ] ];`

`if ( function_exists( 'maspik_is_spam' ) && maspik_is_spam( $fields, 'Contact form' ) ) { wp_die( 'Spam detected' ); }`

Listing the fields explicitly means Maspik analyses exactly the values you care about — never nonces, redirects, tokens, checkboxes or other technical inputs — which keeps detection predictable and avoids false positives.

The honeypot and verification key are read from the request automatically, so there is nothing else to wire up.

Need the reason? `maspik_check_spam()` returns the message to show the visitor, the offending field, and the layer that blocked it.

The original version 2 filter (`maspik_validate_custom_form_fields`) continues to work unchanged, so existing integrations keep running after the upgrade.

Full example: https://wpmaspik.com/documentation/custom-php-form/

= Documentation =

* Getting Started: https://wpmaspik.com/documentation/
* Developer Documentation: https://wpmaspik.com/documentation/developers/
* Support: https://wpmaspik.com/
* Community: https://www.facebook.com/groups/maspik

Maspik is developed with a strong focus on performance, security, privacy and long-term WordPress compatibility.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`, or install it directly from the WordPress plugin directory.
2. Activate it through the *Plugins* screen.
3. That's it — protection starts immediately with sensible defaults.

Open *MASPIK* in the admin menu to fine-tune protection layers, review blocked submissions, or test your configuration in the Playground.

== Frequently Asked Questions ==

= Do I need to configure anything? =

No. Maspik works immediately after activation with sensible defaults. Everything is configurable if you want to fine-tune it.

= Will it work with my form plugin? =

Maspik supports the most popular form builders out of the box (see the list above) as well as WordPress comments and registration. Active integrations are detected automatically and listed on the Dashboard.

= Do I need an API key? =

No. Core protection runs locally with no keys. Optional services such as AbuseIPDB or ProxyCheck use your own key if you choose to enable them.

= Was a real visitor blocked by mistake? =

Open the Logs screen, find the submission, and choose *Mark as Not Spam*. The sender is added to your allow list and won't be blocked again. You can also remove the specific rule that caused the block directly from the log entry.

= Does it slow down my site? =

No. Most checks run locally in PHP, only enabled layers execute, and there is no front-end JavaScript beyond the small guard script used by the honeypot and verification key.

= Does it work with page caching? =

Yes. Protection runs on submission, not on page render, so cached pages are unaffected.

= Will my settings carry over when upgrading from version 2? =

Yes. Settings, logs, your license and your Dashboard connection all carry over automatically on update — no reconfiguration needed.

== Screenshots ==

1. Dashboard — protection status, statistics and recent activity at a glance.
2. Protection — enable and configure each detection layer.
3. Logs — review blocked submissions with the full detection trace.
4. Analytics — detection trends over time.
5. Playground — test your configuration against sample submissions.

== Changelog ==

= 3.0.4 =

* Improved compatibility with Fluent Forms, including AJAX submissions.
* Improved compatibility with Ninja Forms, Contact Form 7, and Elementor Atomic.
* Improved the spam log with clearer blocking reasons, submitted values, and matched rules.
* Added Expand all and Delete all to the log, and Copy all and Clear all to every rule list.
* Improved RTL support and fixed several log display issues.
* Improved log loading and filtering performance.

= 3.0.3 =

**Bug Fixes**

* Fixed several issues with Dashboard synchronization, protection rules, and form validation.
* Fixed comment management issues in the WordPress dashboard.
* Fixed the hidden anti-spam field stretching the page on right-to-left sites, which caused sideways scrolling and layout jumps on mobile.
* Fixed Dashboard settings not being properly applied.
* Improved synchronization for existing sites.

**Improvements**

* Improved protection status indicators in the Dashboard.
* Improved Dashboard connection and ID management.
* Spam log now shows the rule that caught the spam.
* Updated Full Protection messaging with a link to the documentation.

= 3.0.2 =
* Fixed: an issue when 2 patterns exist in the Language protection layer.

= 3.0.1 =
* Export the spam log to CSV from the Logs screen, with every submitted field.
* Fixed: dropdowns, dates, numbers and checkboxes are now recorded in the log — in Gravity Forms and every other form plugin.
* Fixed: Honeypot Trap and Advance key check switched on in the MASPIK Dashboard are now applied to the site.

= 3.0.0 =
* Complete rewrite: modern modular architecture (PSR-4, dependency injection) with identical spam-detection behavior, covered by unit and integration tests.
* New admin experience: Dashboard, Protection, Logs, Analytics, Playground, Advanced and Pro screens.
* Detection Trace: every logged submission records which layers ran, passed, were skipped, timed out or blocked it.
* Submission logging: choose to log nothing, blocked submissions only, or all submissions for debugging, with retention by row count and by age.
* One-click rule actions from the log — whitelist a sender, or remove the exact rule that caused a block.
* Direct POST protection: submissions that bypass the site interface are detected and reported to Matrix.
* New integrations: Elementor Atomic Forms, Divi, JetFormBuilder, Formidable, MetForm, Breakdance, Bit Form, BuddyPress, WooCommerce Checkout and WooCommerce Registration.
* WooCommerce checkout protection on both classic and block checkout, with per-gateway control, zero-total orders and a custom blocked-checkout message.
* Improved IP resolution with a proper proxy trust model, including Cloudflare support.
* Per-layer custom error messages.
* Custom PHP forms: new maspik_is_spam() / maspik_check_spam() helpers, so connecting your own form takes one line. Guard fields are handled automatically. The version 2 filter (maspik_validate_custom_form_fields) still works unchanged.
* Translations for Spanish, French, German, Arabic and Hebrew.
* Requires PHP 7.4+.

== Upgrade Notice ==

= 3.0.4 =
Recommended for everyone, especially Fluent Forms sites.

= 3.0.3 =
Recommended for everyone. Fixes Dashboard synchronization and settings not being applied, and makes protection status clearer.

= 3.0.2 =
Recommended if you use the Language layer (Pro).

= 3.0.1 =
Recommended for everyone on 3.0.0. Restores the missing fields in your spam log, adds CSV export, and applies layers switched on in the MASPIK Dashboard.

= 3.0.0 =
A complete rewrite with the same proven detection engine. Your settings, logs, license and Dashboard connection carry over automatically.
