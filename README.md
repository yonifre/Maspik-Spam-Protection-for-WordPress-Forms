# Maspik – Spam Protection for WordPress Forms

[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/contact-forms-anti-spam?label=wordpress.org)](https://wordpress.org/plugins/contact-forms-anti-spam/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/contact-forms-anti-spam?label=active%20installs)](https://wordpress.org/plugins/contact-forms-anti-spam/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/contact-forms-anti-spam?label=tested%20up%20to)](https://wordpress.org/plugins/contact-forms-anti-spam/)
[![License](https://img.shields.io/badge/license-GPLv3-blue)](https://www.gnu.org/licenses/gpl-3.0.html)

Multi-layer spam protection for forms, comments and WooCommerce. No CAPTCHA, no puzzles — accurate blocking that works out of the box.

Install. Activate. You're protected.

---

## Why Maspik

Most anti-spam plugins rely on one detection technique. Maspik combines many
independent layers into a single engine, so a submission can be judged on its
content, its behaviour and its origin rather than on one signal alone. The
result is stronger protection with fewer false positives, and no interruption
for real visitors.

Most checks run locally in PHP. Only optional features contact an external
service, and only the layers you enable are executed.

## Protection layers

| | |
|---|---|
| **Content** | Forbidden keywords · Email patterns · URL validation · Phone format validation · Character limits · Link limits · Emoji filtering |
| **Behaviour** | Honeypot · Verification Key · Direct POST protection |
| **Origin** | IP blocklists · IP reputation · Proxy and VPN detection |
| **Cloud** | Maspik Matrix, with AI analysis |
| **Pro** | Country and continent restrictions · Language rules |

Every layer is optional and configurable, and each one can carry its own
message for blocked visitors.

## Supported forms

Elementor Forms · Elementor Atomic Forms · Contact Form 7 · Fluent Forms ·
Formidable Forms · Forminator · Ninja Forms · JetFormBuilder · Everest Forms ·
Breakdance Forms · Bricks Builder · Divi Contact Form · Hello Plus · MetForm ·
Bit Form · BuddyPress Registration · WordPress Comments · WordPress
Registration

Pro adds WPForms, Gravity Forms, WooCommerce Registration and WooCommerce
Checkout.

## Your own forms

Built the form yourself? List the fields you want analysed and Maspik tells you
whether to accept the submission. The honeypot and verification key are read
from the request automatically, so there is nothing else to wire up.

```php
$fields = [
    [ 'type' => 'text',     'field_name' => 'name',    'value' => $_POST['name'] ?? '' ],
    [ 'type' => 'email',    'field_name' => 'email',   'value' => $_POST['email'] ?? '' ],
    [ 'type' => 'textarea', 'field_name' => 'message', 'value' => $_POST['message'] ?? '' ],
];

if ( function_exists( 'maspik_is_spam' ) && maspik_is_spam( $fields, 'Contact form' ) ) {
    wp_die( 'Spam detected' );
}
```

Need the reason instead of a yes/no? `maspik_check_spam()` returns the message
to show the visitor, the offending field, and the layer that blocked it.

The version 2 filter `maspik_validate_custom_form_fields` still works
unchanged, so existing integrations keep running after an upgrade.

Full example: <https://wpmaspik.com/documentation/custom-form/>

## Submission log

Every blocked submission records why it was blocked, which layer caught it,
which rule matched, the values that were submitted, the IP and country, and a
full trace of every layer that ran. Mark an entry as *Not Spam* to whitelist
the sender, or remove the exact rule that caught them — both in one click.
Passed submissions can be logged too, to see why something was *not* caught.

## Installation

From WordPress: search for **Maspik** under *Plugins → Add New*, or install
directly from [wordpress.org](https://wordpress.org/plugins/contact-forms-anti-spam/).

From this repository: download the source and copy it into
`wp-content/plugins/contact-forms-anti-spam/`, then activate it from the
*Plugins* screen. The tree here is the released plugin, so no build step is
required.

Requires WordPress 6.2+ and PHP 7.4+.

## About this repository

This repository mirrors the released plugin as published on wordpress.org —
one commit per release, tagged by version in the commit message. It is not the
development tree, so it carries no build tooling or test suite.

## Links

- Plugin page — <https://wordpress.org/plugins/contact-forms-anti-spam/>
- Documentation — <https://wpmaspik.com/documentation/>
- Developer documentation — <https://wpmaspik.com/documentation/developers/>
- Community — <https://www.facebook.com/groups/maspik>
- Pro — <https://wpmaspik.com/>

## License

GPLv3 or later. See <https://www.gnu.org/licenses/gpl-3.0.html>.
