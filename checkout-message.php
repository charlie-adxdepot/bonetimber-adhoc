<?php
/**
 * Custom checkout messages.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Checkout_Message
{
    private const ORDER_RECEIVED_MESSAGE = 'Your order is being processed and a team member will confirm your collection date with you shortly.';

    public function __construct()
    {
        add_filter('woocommerce_thankyou_order_received_text', [$this, 'replace_order_received_message'], 10, 2);
    }

    public function replace_order_received_message(string $message, $order): string
    {
        return self::ORDER_RECEIVED_MESSAGE;
    }

}

new Bonetimber_Checkout_Message();
