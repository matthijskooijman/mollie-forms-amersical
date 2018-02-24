=== Mollie Forms ===
Contributors: ndijkstra
Tags: mollie,registration,form,payments,ideal,bancontact,sofort,bitcoin,belfius,creditcard,recurring,forms
Requires at least: 3.8
Tested up to: 4.9.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Create registration forms with payment methods of Mollie. One-time and recurring payments are possible.

== Description ==

Create registration forms with payment methods of Mollie. One-time and recurring payments are possible.


**Features:**

* Create your own forms
* Set extra fee's per payment method
* One-time and recurring payments
* Fixed or open amount possible
* Configure emails per form
* Refund payments and cancel subscriptions in Wordpress admin
* Style it with your own css classes.


== Frequently Asked Questions ==

= Why can I only choose for One-time payments? =

For recurring payments you will need a supported payment method. You have to activate "SEPA Direct Debit" or "Creditcard" to use recurring payments.

= Can I prefill the form? =

Yes! GET variables are possible to prefill form: ?form_ID_field_INDEX=value (replace ID with form id and INDEX with the field index. First field is 0, second field is 1 etc.)
For filling in the open amount, use "form_ID_amount" and for selecting a price option use "form_ID_priceoption"

= Can I use shortcodes? =

Yes! The following shortcodes are available:

* [rfmp id="ID"] To display the form. Replace ID with the id of the form
* [rfmp-total id="ID"] To display the total raised money. Replace ID with the id of the form
* [rfmp-goal id="ID" goal="1000" text="Goal reached!"] Countdown to your goal. Replace ID with the id of the form, goal must be higher then 0 and the text will be displayed when the goal is reached

= Which hooks are available? =

The following action hooks with parameters are available:
* rfmp_form_submitted,      post ID, $_POST data
* rfmp_customer_created,    post ID, Mollie customer
* rfmp_payment_created,     post ID, Mollie payment
* rfmp_webhook_called,      post ID, payment ID


== Screenshots ==

1. Form settings
2. Form
3. Registrations
4. Registration with subscription
5. Registration without subscription

== Installation ==

= Minimum Requirements =

* PHP version 5.3 or greater
* PHP extensions enabled: cURL, JSON
* WordPress 3.8 or greater


== Changelog ==

= 1.1.5 - 16/02/2018 =
* Updated Mollie API Client to v1.9.6

= 1.1.4 - 07/02/2018 =
* Removed URL rewrite from webhookUrl
* Fixed problem with amount field price options
* Added support page support.wobbie.nl

= 1.1.3 - 24/01/2018 =
* Fixed variables in payment description
* Fixed brakes in emails

= 1.1.2 - 10/01/2018 =
* Value of radio button fields are now stored as intended
* Paid and unpaid payments now visible in export, with new status column
* Description was not stored correctly in database, this is now fixed
* Other minor bugfixes

= 1.1.1 - 10/01/2018 =
* Bugfixes

= 1.1.0 - 03/01/2018 =
* Added add-ons page, with the first add-on Mailchimp.
* It's now possible to add an minimum amount to open prices (if not set the minimum is €1,00)

= 1.0.5 - 20/12/2017 =
* Now only the paid registrations are visible in the exports
* Added prefill parameters for open amount and price option (see FAQ)

= 1.0.4 - 19/12/2017 =
* Upgrade database when plugin is updated
* Added name, email and price option to metadata

= 1.0.3 - 04/12/2017 =
* Fixed bug with radio button values
* Fixed bug with too many brakes in emails

= 1.0.2 - 30/11/2017 =
* Added shortcode [rfmp-goal] to display a countdown to your goal. See the FAQ for more info.
* Added variable {rfmp="url"} to the emails for displaying url of page
* Added action hooks, see the FAQ for more info
* Bugfixes

= 1.0.1 - 29/11/2017 =
* Bugfix

= 1.0.0 - 27/11/2017 =
* Set redirect URL after payment instead of message

= 0.5.2 - 09/11/2017 =
* Use longtext for value field in DB

= 0.5.1 =
* Use translations from wordpress.org

= 0.5.0 =
* New feature: Creating an export of registrations per form
* No error after bank transfer payment
* {rfmp="priceoption"} is now also working in emails
* Updated Mollie Client to 1.9.4

= 0.4.3 =
* Added [rfmp-total] tag to display the total raised amount per form

= 0.4.2 =
* New feature to add shipping costs to price option

= 0.4.1 =
* Variable {rfmp="created_at"} added to email to display date/time

= 0.4.0 =
* Type "Date" added to fields
* You can now fill in your own payment description

= 0.3.13 =
* Added check to prevent a payment without registration

= 0.3.12 =
* Bugfix when using multiple forms on 1 page

= 0.3.11 =
* <a> tag now possible in field label
* Label is now behind the checkbox

= 0.3.10 =
* Removed () when open amount is selected

= 0.3.9 =
* Bugfix multiple email adresses
* Added fixed variable {rfmp="form_title"} for Form title
* Added German language

= 0.3.8 =
* Bugfix

= 0.3.7 =
* Improved variables in emails
* Multiple email addresses possible seperated with comma (,)
* Fix for images in email

= 0.3.6 =
* Added consumer information (name, iban) to payments table
* Added fixed variable {rfmp="payment_id"} for Mollie Payment ID in email templates
* GET variables possible to prefill form: ?form_ID_field_INDEX=value (replace ID with form id and INDEX with the field index. First field is 0, second field is 1 etc.)

= 0.3.5 =
* Added "Number of times" option for subscriptions

= 0.3.3 =
* Tiny fix

= 0.3.2 =
* Fix subscriptions webhook

= 0.3.1 =
* Fixed issue with empty registrations
* Payment and subscription status visible in registration list
* Subscription table bugfix
* Added French translations

= 0.3.0 =
* You can now configure emails per form

= 0.2.3 =
* Using home url now instead of site url
* Fix for frequency label at open amount

= 0.2.2 =
* Registrations are now visible for every admin user

= 0.2.1 =
* Bugfix in open amount


= 0.2.0 =
* You can now add a price option with open amount so the customer can fill in an amount
* Bugfixes

= 0.1.9 =
* Fix for showing success/error message

= 0.1.8 =
* Bugfixes
* Checkbox added for recurring payments

= 0.1.7 =
* Language fix

= 0.1.6 =
* Bug fixes

= 0.1.5 =
* Bug fixes

= 0.1.4 =
* Bug fixes

= 0.1.3 =
* Bug fixes

= 0.1.2 =
* Bug fixes

= 0.1.1 =
* Bug fixes

= 0.1.0 =
* Beta release

== Upgrade Notice ==

