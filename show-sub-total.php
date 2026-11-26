<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Product_Subtotal
{
    public function __construct()
    {
        add_action('woocommerce_after_add_to_cart_button', [$this, 'render_subtotal'], 20);
        add_action('wp_footer', [$this, 'render_assets'], 100);
    }

    public function render_subtotal(): void
    {
        $product = $this->get_product();

        if (!$product || (!$product->is_type('simple') && !$product->is_type('variable'))) {
            return;
        }

        $is_variable = $product->is_type('variable');
        $unit_price = $is_variable ? '' : (string) wc_get_price_to_display($product);

        if (!$is_variable && $product->get_price() === '') {
            return;
        }
        ?>
        <div
            class="bonetimber-product-subtotal"
            data-bonetimber-subtotal
            data-unit-price="<?php echo esc_attr($unit_price); ?>"
            <?php echo $is_variable ? 'hidden' : ''; ?>
            aria-live="polite"
        >
            <span class="bonetimber-product-subtotal__label">Subtotal:</span>
            <span class="bonetimber-product-subtotal__amount" data-bonetimber-subtotal-amount>
                <?php if (!$is_variable) : ?>
                    <?php echo wp_kses_post(wc_price((float) $unit_price)); ?>
                <?php endif; ?>
            </span>
        </div>
        <?php
    }

    public function render_assets(): void
    {
        $product = $this->get_product();

        if (!$product || (!$product->is_type('simple') && !$product->is_type('variable'))) {
            return;
        }

        $currency_position = (string) get_option('woocommerce_currency_pos', 'left');
        $currency_symbol = html_entity_decode(
            get_woocommerce_currency_symbol(),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $is_variable = $product->is_type('variable');
        $initial_unit_price = $is_variable || $product->get_price() === ''
            ? ''
            : (string) wc_get_price_to_display($product);
        ?>
        <style>
            .bonetimber-product-subtotal {
                display: block;
                clear: both;
                box-sizing: border-box;
                width: 100%;
                margin-top: 14px;
                padding: 11px 15px;
                background: #f2f5f5;
                border-left: 4px solid #ffb74d;
                border-radius: 4px;
                color: #24333a;
                font-size: 17px;
                line-height: 1.35;
            }

            .bonetimber-product-subtotal[hidden] {
                display: none;
            }

            .bonetimber-product-subtotal__label {
                font-weight: 600;
            }

            .bonetimber-product-subtotal__amount {
                margin-left: 4px;
                color: #17262d;
                font-weight: 800;
            }
        </style>
        <script>
            (function() {
                var currencySymbol = <?php echo wp_json_encode($currency_symbol); ?>;
                var currencyPosition = <?php echo wp_json_encode($currency_position); ?>;
                var decimalSeparator = <?php echo wp_json_encode(wc_get_price_decimal_separator()); ?>;
                var thousandSeparator = <?php echo wp_json_encode(wc_get_price_thousand_separator()); ?>;
                var decimals = <?php echo (int) wc_get_price_decimals(); ?>;
                var isVariable = <?php echo $is_variable ? 'true' : 'false'; ?>;
                var initialUnitPrice = <?php echo wp_json_encode($initial_unit_price); ?>;

                function formatAmount(value) {
                    var fixed = Number(value).toFixed(decimals).split('.');
                    var whole = fixed[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
                    var amount = decimals > 0 ? whole + decimalSeparator + fixed[1] : whole;

                    if (currencyPosition === 'right') {
                        return amount + currencySymbol;
                    }

                    if (currencyPosition === 'left_space') {
                        return currencySymbol + ' ' + amount;
                    }

                    if (currencyPosition === 'right_space') {
                        return amount + ' ' + currencySymbol;
                    }

                    return currencySymbol + amount;
                }

                function getSelectedVariationPrice(form) {
                    var variationIdInput = form.querySelector('input.variation_id');
                    var variationId = variationIdInput ? parseInt(variationIdInput.value, 10) : 0;
                    var variations;

                    if (!variationId) {
                        return '';
                    }

                    try {
                        variations = JSON.parse(form.getAttribute('data-product_variations') || '[]');
                    } catch (error) {
                        return '';
                    }

                    for (var i = 0; i < variations.length; i++) {
                        if (parseInt(variations[i].variation_id, 10) === variationId) {
                            return variations[i].display_price;
                        }
                    }

                    return '';
                }

                function createSubtotal(form) {
                    var subtotal = form.querySelector('[data-bonetimber-subtotal]');

                    if (!subtotal) {
                        subtotal = document.createElement('div');
                        subtotal.className = 'bonetimber-product-subtotal';
                        subtotal.setAttribute('data-bonetimber-subtotal', '');
                        subtotal.setAttribute('data-unit-price', initialUnitPrice);
                        subtotal.setAttribute('aria-live', 'polite');
                        subtotal.innerHTML =
                            '<span class="bonetimber-product-subtotal__label">Subtotal:</span>' +
                            '<span class="bonetimber-product-subtotal__amount" data-bonetimber-subtotal-amount></span>';

                        if (isVariable) {
                            subtotal.hidden = true;
                        }
                    }

                    if (subtotal.parentElement !== form.parentElement || subtotal.previousElementSibling !== form) {
                        form.insertAdjacentElement('afterend', subtotal);
                    }

                    return subtotal;
                }

                document.querySelectorAll('form.cart').forEach(function(form) {
                    var subtotal = createSubtotal(form);
                    var amount = subtotal.querySelector('[data-bonetimber-subtotal-amount]');
                    var quantity = form ? form.querySelector('input.qty') : null;

                    if (!form || !amount) {
                        return;
                    }

                    function updateSubtotal() {
                        var unitPrice = parseFloat(subtotal.getAttribute('data-unit-price'));
                        var qty = quantity ? parseFloat(quantity.value) : 1;

                        if (!isFinite(unitPrice) || !isFinite(qty)) {
                            return;
                        }

                        amount.textContent = formatAmount(unitPrice * qty);
                        subtotal.hidden = false;
                    }

                    if (quantity) {
                        quantity.addEventListener('input', updateSubtotal);
                        quantity.addEventListener('change', updateSubtotal);
                    }

                    if (isVariable) {
                        subtotal.setAttribute('data-unit-price', getSelectedVariationPrice(form));
                    }

                    updateSubtotal();

                    if (window.jQuery && form.classList.contains('variations_form')) {
                        window.jQuery(form)
                            .on('found_variation', function(event, variation) {
                                if (!variation || typeof variation.display_price === 'undefined') {
                                    return;
                                }

                                subtotal.setAttribute('data-unit-price', variation.display_price);
                                updateSubtotal();
                            })
                            .on('hide_variation reset_data', function() {
                                subtotal.setAttribute('data-unit-price', '');
                                subtotal.hidden = true;
                            });
                    }
                });
            })();
        </script>
        <?php
    }

    private function get_product()
    {
        if (!is_product()) {
            return null;
        }

        $product = wc_get_product(get_queried_object_id());

        return $product instanceof WC_Product ? $product : null;
    }
}

new Bonetimber_Product_Subtotal();
