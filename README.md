This plugin install a new payment gateway for Woocommerce, so as to integrate the PUNTOLIS functionalities.

The plugin create:
- a new payment option into the checkout
- the pdf of deposit, with the barcode, so that it's possible to pay the order
- the barcode data in order's detail
- order confirmation's email with pdf of deposit

The order paid with PUNTOLIS will be set to pending status.
Once a day the plugin check the puntolis payments report and update the order status if the order is paid.

Tested with
- Wordpress 6.1.1
- Woocommerce 7.2.2

**Requirements**
- PHP zip extension
