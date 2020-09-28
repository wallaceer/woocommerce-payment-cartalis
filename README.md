This plugin install a new payment gateway for Woocommerce, so as to integrate the CARTALIS functionalities.

The plugin create:
- a new payment option into the checkout
- the pdf of deposit, with the barcode, so that it's possible to pay the order
- the barcode data in order's detail

The order paid with CARTALIS will be set to pending status.
Once a day the plugin check the cartalis payments report and update the order status if the order is paid.

**Requirements**
- PHP zip extension 