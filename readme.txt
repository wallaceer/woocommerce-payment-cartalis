=== WooCommerce Payment PUNTOLIS ===
Contributors: Walter Santi
Tags: payment,puntolis
Tested up to: 6.1.1
Stable tag: 1.2.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==
This plugin install a new payment gateway for Woocommerce, so as to integrate the PUNTOLIS functionalities.

The plugin create:
- a new payment option into the checkout
- the pdf of deposit, with the barcode, so that it's possible to pay the order
- the barcode data in order's detail

The order paid with PUNTOLIS will be set to pending status.
Once a day the plugin check the puntolis payments report and update the order status if the order is paid.

**Requirements**
- PHP zip extension
