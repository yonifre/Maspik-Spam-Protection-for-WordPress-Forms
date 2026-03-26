# Changelog #

## 2.5.5 - 20/08/2025 ##
* New (Beta) feature - AI spam check - Give it a try!
* For Developers - Add hooks to disable spam check for specific forms link: [Developers Hooks](https://wpmaspik.com/documentation/developers/?readme-file)
* Fixed - Fix issue where Breakdance form didn't recognize Spam key check.
* Fixed - Fix issue where search form was not submitting properly in some cases.


## 2.5.4 - 09/08/2025 ##
* New feature - Add URL field blocker.
* New feature - Add support for Breakdance Builder forms.
* Fixed - Fix issue where spam statistics dashboard give php error when no spam was blocked in some cases.

## 2.5.3 - 24/05/2025 ##
* Fixed - Fix php error where function was call to early.
* We want to grow and improve the experience! so we need your help! please:
  * Share your feedback and suggestions to our [Suggestion Box](https://wpmaspik.com/#suggestions?readme-file).
  * Give as 5 stars review on [WordPress.org](https://wordpress.org/support/plugin/contact-forms-anti-spam/reviews/#new-post).
  * Spread the word and write a Guide/Vlog/Post about Maspik, and receive a Pro license on us.

## 2.5.2 - 23/05/2025 ## 
* New feature - adding a feedback form to the settings page to help us improve the plugin.
* Fixed an issue where some hidden fields had duplicate IDs.
* Fixed database update issue when upgrading from version 1.x to version 2.0 or higher.
* Improved - Enhanced handling and performance for processing lengthy textarea content.


## 2.5.1 - 30/04/2025 ## 
* Bug fix - Fixed an issue where search forms were not submitting properly in certain scenarios.
* We want to grow and improve the experience! so we need your help! please:
  * Share your feedback and suggestions to our [Suggestion Box](https://wpmaspik.com/#suggestions?readme-file).
  * Give as 5 stars review on [WordPress.org](https://wordpress.org/support/plugin/contact-forms-anti-spam/reviews/#new-post).
  * Spread the word and write a Guide/Vlog/Post about Maspik, and receive a Pro license on us.


## 2.5.0 - 22/04/2025 ## 
* Improve - Improved UI/UX of the entire plugin.
* New feature! - Add Spam statistics dashboard page.
* New feature - Add spam statistics data to admin dashboard.
* New feature - Add reset all settings to default (Factory reset).
* New feature - Adding support for Custom PHP forms by using the [filter](https://wpmaspik.com/documentation/custom-php-form/?readme-file).
* Bug fix - Fixed form validation issues that occurred when forms were placed inside popups and WP Rocket caching was enabled with strict settings.
* Bug fix - Fix translation issue with WP version 6.7, load too early.
* Bug fix - Fixed issue where maximum links limit was not being set through the Dashboard API.
* Improvement - Compatibility with WP version 6.8


## 2.4.4 - 20/03/2025 ## 
* Announcement - Advance key check is now officialy released and not anymore in ongoing exprement.
* Improved - Enhanced security by hiding passwords in Spam log
* Improved - Disabled autocomplete functionality in WooCommerce registration forms for better security


## 2.4.3 - 19/02/2025 ## 
* Fixed - Fix Elementor Bot detector option not working wall (When page source url is empty).
* Fixed - Fix detection string in Textarea field with regex/wildcard format.
* Improve - Improve detection string in Textarea field: Now ignore ,!?. at the end of the string for better detection.

## 2.4.2 - 18/02/2025 ## 
* New feature - Add option to block submissions with emojis in textarea fields.
* New feature - Added support for Hello Plus form builder plugin.


## 2.4.1 - 05/02/2025 ## 
* Improved - Improve IP detection.
* Improved - Text area field blacklist check now supports advanced pattern matching: Use wildcards (*) for flexible blocking - for example, adding "*seo*" will block any text containing "seo" anywhere, like "seoexpert". This gives more control for advanced users to block specific content variations.
* Fixed - Fix error in phone number validation for GravityForms.


## 2.4.0 - 30/01/2025 ## 
* Announcement - In this version we made some improvements to reduce false positives. read more in the [announcement page](https://wpmaspik.com/maspik-2-4-0-smarter-spam-blocking-fewer-false-positives/?readme-file)
* Improved - Text and textarea field blacklist's now match exact words only, preventing false positives from partial word matches (e.g., "ad" won't block "shade", but "seo" will block "Seo expert").
* Improved - Improve email field blacklist check - Now checks both if an email exactly matches or is contained within blacklisted patterns, (e.g. "Seo" will block "seoexpert@gmail.com").
* Improved - Made settings page text clearer and easier to understand.
* Improved - Improve link detection in text area fields.
* Improved - Improve text area field character limit check.
* Fixed - Fix JS error on settings page.
* Fixed - Fix Serbian language detection.


## 2.3.0 - 18/01/2025 ##
* Improved WP registration form support
* Improved Comments form support
* Improved WooCommerce registration form support
* Improved code performance
* Improved Elementor form support
* Improved default settings auto activation
* Removed update support from Maspik versions smaller than 2.0.0 to improve stability and performance


## 2.2.14 - 05/01/2025 ##
* Happy New Year! may all your spam be blocked!
* Improvement - Improve Database performance
* Improvement - Improve code performance 
* New feature! - Advance key check formaly known as "Time Check", better performance and more accurate.


## 2.2.13 - 19/12/2024 ##
* Fixed HTML error in settings page that prevented select fields from being saved.

## 2.2.12 - 16/12/2024 ##
* New feature! - Add support in Numverify API for phone number validation.
* Fixed - fix error in Formidable forms.
* Fixed - Fixed an issue where the Dashboard ID was not displaying correctly.
* Fixed - Fixed an issue where spam log entry limit was not working as in some cases.


## 2.2.11 - 05/12/2024 ##
* Improvement - Improve text in settings page and translation
* Bug fix - Fix UI glitch in settings page

## 2.2.10 - 03/12/2024 ##
* Improvement - Compatibility with WP version 6.7

## 2.2.9 - 22/11/2024 ##
* Fixed: Removed autofill attribute from honeypot fields to improve compatibility with AMP pages
* Improvement - Spam log default save entries max number is now 1000 (was 2000)

## 2.2.8 - 08/11/2024 ##
* New Feature - Add support in BuddyPress forms.
* Improvement - Improve layout of Playground form.
* Improvement - add page link in spam log.
* Improvement - improve time block check to reduce false positive.
* Improvement - Add spanish translation.


## 2.2.7 - 16/10/2024 ##
* Bug fix - Fix error in Contact Form 7 with checkbox field in some cases.

## 2.2.6 - 15/10/2024 ##
* Improvement - update license manager library
* Bug fix - Fix spam message validation for phone field.

## 2.2.5 - 06/10/2024 ##
* Improvement - Editor can publish comments without validation check.
* Bug fix - Fix phone number limit digit check on Elementor form.

## 2.2.4 - 04/10/2024 ##
* Bug fix - Fix error in Country check for some cases.
* Bug fix - Fix error in AbuseAPI check for some cases.
* Remove - shortcode option in text-area field, because can be confuseding.


## 2.2.3 - 01/10/2024 ##
* New Feature - IP verification, add IP verification usage activity to Maspik dashboard.
* Improvement - Improve code performance in CF7 & Elementor forms, validate spam up to 50% faster
* Improvement - Improve option to mark "Not a Spam" on Spam log.
* Improvement - Improve form data UI in Spam log.
* Improvement - Change date format in Spam log to Wordpress format.

## 2.2.2 - 14/09/2024 ##
* Improvement - Better caching mechanism for IP address verification

## 2.2.1 - 10/09/2024 ##
* Bug Fix - Fixed an issue where settings were not being saved correctly in certain server environments
* Improvement - Scheduled deletion of outdated IP check data twice daily for improved performance

## 2.2.0 - 08/09/2024 ##
* Improvement - Improve UI/UX
* Improvement - Make main block setting as ON by default. You can deactivate settings in the settings page.
* Improvement - Forms are now supported by default. You can deactivate support for specific forms in the settings page.

## 2.1.6 - 30/08/2024 ##
* New feature - added option to check if sender's IP address exists in Maspik blacklist database.
* Bug fix - Fixed PHP syntax error in some environments.

## 2.1.5 - 21/08/2024 ##
* Bug fix - Fixed some toggle buttons in the admin setting page that did not work properly.

## 2.1.4 - 15/08/2024 ##
* Bug fix - fix "parsererror" in honeypot check on Elementor form.

## 2.1.3 - 13/08/2024 ##
* New Feature - Added support for JetFormBuilder.
* New Feature - Added support for Everest Forms.
* New Feature - Add option to mark "Not a Spam" on Spam log.
* Bug Fix - Fix vulnerability, thanks to @Artyom from CleanTalk inc.

## 2.1.2 - 26/07/2024 ##
* Bug Fix - Fix error when Max links set on 0 

## 2.1.1 - 24/07/2024 ##
* Improvement - Improve Honeypot check for Elementor + CF7 + Comments + Woocommerce Review + Registration 
* Bug Fix - Fix css glitch on some casese with new Honeypots

## 2.1.0 - 22/07/2024 ##
* New Feature - Time check (If spent less then 2 secund in site - Spam)
* New Feature - Honeypot field (If not ampty- Spam )
* New Feature - Advance Honeypot field (If Js Year is diffrent from server year - Spam)

## 2.0.6 - 19/07/2024 ##
* Improvement - Improve spam check performance 
* Bug Fix - Fix error appearing in Country check in some environments

## 2.0.5 - 16/07/2024 ##
* Bug Fix - Fix phone check

## 2.0.4 - 16/07/2024 ##
* New Feature - Download Spam-log as CSV
* Bug Fix - Fix languages check
* Bug Fix - Fix error appearing when spam is caught in some environments
* Improvement - Add option to insert question mark (?) in wildcard pattern in phone field


## 2.0.3 - 12/07/2024 ##
* Improvement - Improve Dashboard(Maspik API) - add more fields in WpMaspik site for Pro users  
* Bug fix - Fix languages check   
* Bug fix - Fix toggel option for some condition 

## 2.0.2 - 11/07/2024 ##
* Bug fix - fixed some toggel options (Allow usage tracking, Disable comments, Add country name to emails) that weren't stable on some aviroment. 
* Change plugin footer text

## 2.0.1 - 10/07/2024 ##
* Bug fix - removed disable comments option as it is not stable on some aviroment. 

## 2.0.0 - 10/07/2024 ##
* Major Update - New user experience with a fresh & clear design.
* Code Upgrade - 80% of the plugin code has been revamped.
* Database Enhancement - Plugin settings are now saved in a separate table.
* New Feature - Added support for NinjaForms.
* New Feature - Added the ability to limit the number of characters in various fields.
* Spam Log Upgrade - Improved spam log functionality (note: previous spam log content will be deleted).
* Enhancement - Import/Export option become a free option (Not pro).

## 1.0.5 -  15/05/2024 ##
* Bug fix - Fix Woocommerce spam check

## 1.0.4 -  30/04/2024 ##
* Bug fix - Fix comment spam check

## 1.0.3 -  07/04/2024 ##
* Improvement - Add maximum number of characters in Text Area fields
* Improvement - Improve Playground form
* Improvement - Improve code performance 
* Improvement - Improve spam check in Text Area fields

## 1.0.2 -  22/03/2024 ##
* Improvement - Improve check in Proxycheck API.
* Improvement - Add language name to spam log when required/forbidden language triggers spam.
* Improvement - Maspik API language options integration.

## 1.0.1 -  14/03/2024 ##
* Bug fix - Remove unnecessary css file in admin area
* Bug fix - Disable cron checker log if no key is set

## 1.0.0 -  12/03/2024 ##
* Enhancement - New License Manager (Please delete your current license and re-activate it if it does not work)
* Enhancement - Block based on country/language becomes a PRO feature. see <a target##"_blank" href##"https://wpmaspik.com/announcement-changes-to-maspik-plugin/?1.0-inplugin-readme">article</a> for detail
* Improvement - Improve code performance
* New Feature - Options to create custom validation error for more options
* Bug fix - Fix in Playground form  

## 0.13.0 -  01/03/2024 ##
* Improvement - code performance improvement throughout the plugin code
* Improvement - Minor UI/UX improvements to the settings page
* New Feature - Options to create custom validation error for few options
* Bug fix - Fix Brick forms compatibility  
* Bug fix - Fix Phone field in Playground form

## 0.12.4 -  24/02/2024 ##
* New Feature - Export Import (For Pro users only) 
* Bug fix - Fixed bug in the GravityForms - confirm email field.

## 0.12.3 -  18/02/2024 ##
* Improvement  -  Improve code performance Thanks to @pluginvulnerabilities.com

## 0.12.2 -  09/02/2024 ##
* Improvement - Add support to Bricksbuilder forms!
* Improvement - Add support to Fluentforms!

## 0.12.1 -  04/02/2024 ##
* Bug fix - Fix error in old php versions 

## 0.12.0 -  01/02/2024 ##
* New Feature - New Playground - test your entries to see if they will be blocked 
* Improvement - Add support to Forminator forms!
* Improvement - New style for the setting page
* Performance - Improve code performance

## 0.11.0 -  27/01/2024 ##
* Improvement - Add support to Formidable forms!
* Performance - Improve code performance

## 0.10.7 -  21/01/2024 ##
* Performance  -  Improve code performance Thanks to @pluginvulnerabilities.com
* Disabling of Maspik human verification check - because not stable yet.

## 0.10.6 -  11/01/2024 ##
* Tweak - option to add country name at the bottom of email content - to identify and block countries that send spam.
* Improvement - better organization of the Maspik dashboard menu.

## 0.10.5 -  19/12/2023 ##
* Improvement  - shering non-sensitive information for helping block more spam - under Maspik "More option" page (Most down option - please select V) 

## 0.10.4 -  08/12/2023 ##
* Changed the way visitor IP is checked in IP/country blocking (for patchstack.com)

## 0.10.3 -  27/11/2023 ##
* Performance  -  Improve code performance
* Bug fix - Fix spam check in some situation


## 0.10.2 -  26/11/2023 ##
* Performance  -  Improve code performance
* Bug fix - Fix Textarea spam check, Thanks to @tauri77

## 0.10.1 -  23/11/2023 ##
* Performance  -  Improve code performance
* Tweek - Introduce Maspik human verification - New Bot capture - BETA 

## 0.10.0 -  23/11/2023 ##
* Performance  -  Improve code performance
* Bug fix - fix vulnerability problem
* Tweek - Adding languages to the block/allow list

## 0.9.3 -  22/11/2023 ##
* Bug fix - fix vulnerability problem, thanks to patchstack.com

## 0.9.2 -  17/11/2023 ##
* Improvement - Support multisite wordpress setup 
* Performance  -  Improve code performance.

## 0.9.1 -  16/10/2023 ##
* Bug fix - Tel field incorrectly checked for spam  

## 0.9.0 -  14/10/2023 ##

* New Feature - Wildcard pattern accepted in text-field/email-field/ Tel-field!
* Improvement - Improve and add more in the API options (https://wpmaspik.com/).
* Performance  -  Improve code performance.

## 0.8.3 -  04/09/2023 ##
* New Feature - Automatically adding spam phrases from the MASPIK API (PRO)(BETA)	
* Bug fix - Ip detect wrong in some servers  
* Improvement - Improve and add more in the API options (https://wpmaspik.com/).

## 0.8.2 -  29/07/2023 ##
* Improvement - Add Russian translation thanks to Andrey

## 0.8.1 -  10/07/2023 ##
* Bug fix - Fix ip detect wrong in some cases  

## 0.8 -  12/05/2023 ##
* New Feature - Add option to Completely Disable Comments in WordPress.
* Performance  -  Improve code performance.
* Improvement - Improve and add more in the API options (https://wpmaspik.com/).

## 0.7.11 -  11/05/2023 ##
* Performance  -  Improve code performance
* Improvement - Improve API options 

## 0.7.10 -  07/04/2023 ##
* Adaptation to WP version 6.2

## 0.7.9 -  25/02/2023 ##
* Bug fix (Please update ASAP!)

## 0.7.8 -  07/10/2022 ##
* Adaptation to WP version 6.1

## 0.7.7 -  07/10/2022 ##
* Bug fix - Fix Presserror problem    

## 0.7.6 -  29/09/2022 ##
* Bug fix - Fix Name field check is is Array in Wpforms & GravityForms  

## 0.7.5 -  12/08/2022 ##
* Bug fix - Fix Block Domain Email (Ex: xyz.com) in some servers  

## 0.7.4 -  01/08/2022 ##
* New Feature - Connection to Proxycheck.io API (Thanks to josephcy95).
* New Feature - Connection to AbuseIPDB.com API (Thanks to josephcy95).
* Improvement - Add possibility to filter entire CIDR range such as 134.209.0.0/16 in IP blocklist field. (Thanks to josephcy95).
* Improvement - Improve layout of Spam log.

## 0.7.3 -  29/07/2022 ##
* Bug fix - Prevents letters from becoming lowercase in regex format on tel field.

## 0.7.2 -  19/07/2022 ##
*  Bug fix - Error message not displaying in some servers  
*  Bug fix - Spam log not showing in some servers
*  Improvement - Add the ability to disable spam log in the Option page  

## 0.7.1 -  12/07/2022 ##
*  Improvement - Add Option page  

## 0.7.0 -  12/07/2022 ##
*  Improvement - Add support in Wordpress comments 
*  Improvement - Add support in Wordpress registration 
*  Improvement - Add support in Woocommerce review (pro) 
*  Improvement - Add support in Woocommerce registration (pro)

## 0.6.6 -  04/07/2022 ##
* Performance  -  Improving code performance, Error log loud only when needed (thanks to Marius) 

## 0.6.5 -  03/06/2022 ##
*  Bug fix - fix Block empty source URL option

## 0.6.4 -  03/06/2022 ##
*  Bug fix - Disable Spampixel 

## 0.6.3 -  31/05/2022 ##
*  Bug fix - Fix lower/uppercase letter in Country field 
*  New Url to API site 

## 0.6.1 -  30/05/2022 ##

*  Improvement - Add Spam log counter to Spam log menu title
*  Bug fix - Allow only specific country option in CF7

## 0.6.0 -  27/05/2022 ##
*  Improvement - Smart bot capture (spampixel)
*  Improvement - Add Gravityforms support - pro feature
*  Improvement - Add Wpforms support - pro feature   

## 0.5.8 -  27/04/2022 ##
*  Tweek - Add Editor access to Spam-log 


## 0.5.7 -  20/04/2022 ##
*  Tweek - Improve Text field spam lockup 
*  Bug fix - Fix Allow only specific country option

## 0.5.6 -  09/04/2022 ##
* Improvement - Add option to put an end of Email in email field, like: @test.com 

## 0.5.5 -  06/04/2022 ##
*  Thanks to @Fiona_Fars, Improve explanation content in the setting pages.


## 0.5.4 -  24/01/2022 ##
* Improvement - Load API php file only if mark it or already use it
* Improvement - Ready for Wordpress 5.9

## 0.5.3 -  25/11/2021 ##
* Improvement - Limit spam log to max 100 entrees (To prevent DB overload)

## 0.5.2 ##
* Add a minimum requires version for php (7)

## 0.5.1 ##
* Bug fix - Language api

## 0.5 ##
* Performance  -  Improving code performance
* Tweek added  -  Change country drop down list
* Tweek added  -  Show your API list in the main setting page

## 0.4.3 ##
* Add translation   - Add Hebrew translation

## 0.4.2 ##
* Bug fix  - fix Block empty source URL option 

## 0.4.1 ##
* Bug fix  - fix PHP error in 0.4.0v 

## 0.4.0 ##
upgrade_notice: 'You may need to activate the plugin again after the update, as the plugin name changed'
* Tweek added  -  Connect your site to Spam API 
* Tweek added  -  Variety of new options
* Performance  -  Improving code performance

## 0.3.0 ##
upgrade_notice: 'You may need to activate the plugin again after the update, as the plugin name changed'
* Tweek added  -  Allow only specific country 
* Tweek added  -  Block text-field with more than X characters 
* Tweek added  -  Ability to block textarea-field with any Russian character 
* Tweek added  -  Email Blacklist field support Regex 
* Performance  -  Improving code performance

## 0.2.2 ##
upgrade_notice: 'You may need to activate the plugin again after the update, as the plugin name changed'
* Bug fix  - Fix phone filed & affect Language check only on textarea. 


## 0.2.1 ##
* Bug fix  - Fix phone filed in CF7 always blocked. 

## 0.2.0 ##
* Tweek added  - Spam log 
* Bug fix  - Not blocking Email field. 

## 0.1.0 ##
*Tweek added  - Add plugin support to- Contact form 7 
*Tweek added  - Custom phone validation (Add your format) 

## 0.0.5 ##
*Bug fix  - Fix php error. 

## 0.0.4 ##
*Bug fix  - Fix bug in textarea field. 

## 0.0.3 ##
*Tweek added  - Add Spam block counter

## 0.0.2 ##
*Tweek added  - Block sending if not contains one character from main site language (Hebrew only for now) 

## 0.0.1 ##
First release