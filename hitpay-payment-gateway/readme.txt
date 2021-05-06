=== HitPay Payment Gateway for WooCommerce ===
Contributors: HitPay
Tags: hitpay payments, woocommerce, payment gateway, hitpay, pay with hitpay, credit card, paynow, wechatpay, alipay
Requires at least: 4.0
Tested up to: 5.6.2
Stable tag: 2.7
Requires PHP: 5.5
WC requires at least: 2.4
WC tested up to: 5.0.0
License: MIT

HitPay Payment Gateway Plugin allows HitPay merchants to accept PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments.

== Description ==

HitPay Payment Gateway Plugin allows HitPay merchants to accept PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments.

This plugin would communicate with 3rd party HitPay payment gateway(https://www.hitpayapp.com/) in order to process the payments.

Merchant must create an account with HitPay payment gateway(https://www.hitpayapp.com/).

Pay only per transaction. No monthly, setup, admin or any hidden service fees.

Merchant once created an account with HitPay payment gateway(https://www.hitpayapp.com/), they can go to thier HitPay dashboard and choose the payment options they would to avail for their site.

And merchant need to copy the API keys and Salt values from the HitPay Web Dashboard under Settings > Payment Gateway > API Keys

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'HitPay Payment Gateway'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `hitpay-payment-gateway.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `hitpay-payment-gateway.zip`
2. Extract the `hitpay-payment-gateway` directory to your computer
3. Upload the `hitpay-payment-gateway` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Configuration ==

1. Go to WooCommerce settings
2. Select the "Payments" tab
3. Activate the payment method (if inactive)
4. Set the name you wish to show your users on Checkout (for example: "HitPay or Creditcard")
5. Fill the payment method's description (for example: "Pay with HitPay")
6. Copy the API keys and Salt values from the HitPay Web Dashboard under Settings > Payment Gateway > API Keys
7. Select the payment gateway logos.
8. Click "Save Changes"
9. All done!

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. You can copy the API keys and Salt values from the HitPay Web Dashboard under Settings > Payment Gateway > API Keys.

= Where can I find more documentation on your service? =

You can find more documentation about our service on our [get started](https://hitpay.zendesk.com/hc/en-us/sections/360002421091-About-HitPay) page, our [technical documentation](https://hitpay.zendesk.com/hc/en-us/articles/900004225243-HitPay-WooCommerce-Payment-Gateway-Singapore-How-to-update-the-HitPay-WooCommerce-Plugin-) page or our [resources](https://hit-pay.com/docs.html) page.
If there's anything else you need that is not covered on those pages, please get in touch with us, we're here to help you!

= Where can I get support? =

The easiest and fastest way is via our live chat on our [website](https://www.hitpayapp.com/) or via our [contact form](https://www.hitpayapp.com/contactus).

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Normal checkout with HitPay Payment Gateway.

== Changelog ==

= 2.0 =
* Initial release.

== Upgrade Notice ==
= 2.1 =
- Removed payment logos select option as mandatory.
- Internal server error catched and updating the order status as failed.
- Displaying the payment type used by customer to make payment in the admin order view.

= 2.2 =
- Resolved a bug

= 2.3 =
- Resolved a bug

= 2.4 =
- If customer click back button, check the order status if paid before cancelling the order.

= 2.5 =
- Fix payment method text position

= 2.6 =
- Fixed - webhook executing multiple times.

= 2.7 =
- Added simulator for CURL if not enabled on the server
- Sending sitename to the gateway(helpful for the multi sites)
