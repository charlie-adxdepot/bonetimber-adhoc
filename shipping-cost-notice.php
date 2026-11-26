<?php
/**
 * Shows an interstate shipping notice in the cart's shipping calculator
 * whenever the selected State is anything other than South Australia.
 *
 * The cart totals block is re-rendered by WooCommerce via AJAX (after
 * "Update" and quantity changes), so the notice is re-evaluated on those
 * events as well as on every State change.
 *
 * The shipping calculator form is also opened automatically on page load
 * (and after each re-render) instead of staying behind "Change address".
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Shipping_Cost_Notice
{
    private const HOME_STATE = 'SA';
    private const MESSAGE = 'Please note that we are based in South Australia, and additional shipping charges may apply for deliveries to interstate locations.';

    public function __construct()
    {
        add_action('wp_footer', [$this, 'render_assets'], 100);
    }

    public function render_assets(): void
    {
        if (!is_cart()) {
            return;
        }
        ?>
        <style>
            .bonetimber-shipping-notice {
                margin: 0 0 16px;
                padding: 10px 14px;
                background: #fdf3e1;
                border-left: 4px solid #ffb74d;
                border-radius: 4px;
                color: #24333a;
                font-size: 14px;
                line-height: 1.4;
            }

            .bonetimber-shipping-notice[hidden] {
                display: none;
            }
        </style>
        <script>
            (function() {
                var homeState = <?php echo wp_json_encode(self::HOME_STATE); ?>;
                var message = <?php echo wp_json_encode(self::MESSAGE); ?>;
                var stateSelector = '#calc_shipping_state';

                // WooCommerce renders the calculator collapsed (inline display:none) behind
                // the "Change address" link, so open it for the customer on load.
                function openCalculator() {
                    var form = document.querySelector('.shipping-calculator-form');
                    var toggle = document.querySelector('.shipping-calculator-button');

                    if (!form) {
                        return;
                    }

                    form.style.display = 'block';

                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                }

                function updateNotice() {
                    var state = document.querySelector(stateSelector);
                    var notice = document.querySelector('.bonetimber-shipping-notice');
                    var row;
                    var shouldShow;

                    if (!state) {
                        if (notice) {
                            notice.remove();
                        }

                        return;
                    }

                    // An empty value means no state chosen yet; don't warn until one is picked.
                    shouldShow = state.value !== '' && state.value !== homeState;

                    if (!notice) {
                        if (!shouldShow) {
                            return;
                        }

                        row = state.closest('.form-row') || state.parentElement;
                        notice = document.createElement('p');
                        notice.className = 'bonetimber-shipping-notice';
                        notice.setAttribute('role', 'status');
                        notice.textContent = message;
                        row.insertAdjacentElement('afterend', notice);
                    }

                    notice.hidden = !shouldShow;
                }

                // Delegated, so it survives WooCommerce re-rendering the cart totals.
                document.addEventListener('change', function(event) {
                    if (event.target && event.target.matches && event.target.matches(stateSelector)) {
                        updateNotice();
                    }
                });

                if (window.jQuery) {
                    // select2/selectWoo fire jQuery events rather than native change events.
                    jQuery(document.body).on('change', stateSelector, updateNotice);
                    jQuery(document.body).on('updated_wc_div updated_cart_totals updated_shipping_method country_to_state_changed', function() {
                        setTimeout(updateNotice, 0);
                    });
                    // These re-render the totals block, which brings the calculator back collapsed.
                    jQuery(document.body).on('updated_wc_div updated_cart_totals', function() {
                        setTimeout(openCalculator, 0);
                    });
                    // WooCommerce's cart.js hides the calculator in its own ready handler, which runs
                    // after this inline script, so open it once that has finished.
                    jQuery(function() {
                        setTimeout(openCalculator, 0);
                    });
                } else {
                    window.addEventListener('load', openCalculator);
                }

                openCalculator();
                updateNotice();
            })();
        </script>
        <?php
    }
}

new Bonetimber_Shipping_Cost_Notice();
