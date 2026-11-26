<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Product_Unit_Measure
{
    private const SUPPORTED_UNITS = [
        'Length',
        'LM',
        'Sheet',
        'Each',
        'Pk',
        'Roll',
        'EA'
    ];

    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_product_unit_measure_field']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_unit_measure']);
        add_filter('woocommerce_available_variation', [$this, 'add_variation_unit_measure_data'], 10, 3);
        add_action('wp_footer', [$this, 'append_to_dynamic_price'], 99);
        add_action('wp_footer', [$this, 'append_cart_unit_measure_column'], 99);
    }

    /**
     * Adds the editable "um" product meta manager to Product Data > General.
     *
     * This keeps the unit of measure editable from the WooCommerce product
     * screen while preserving the existing "um" meta key used on the frontend.
     */
    public function add_product_unit_measure_field(): void
    {
        global $post;

        if (!$post instanceof WP_Post) {
            return;
        }

        woocommerce_wp_select([
            'id' => 'um',
            'label' => __('Unit of Measure', 'bonetimber'),
            'description' => __('Optional unit shown after the product price, such as /LM or /Sheet.', 'bonetimber'),
            'desc_tip' => true,
            'options' => $this->get_supported_unit_options(),
            'value' => trim((string) get_post_meta($post->ID, 'um', true)),
        ]);
    }

    /**
     * Saves the Product Data > General "um" field for the unit measure manager.
     */
    public function save_product_unit_measure(WC_Product $product): void
    {
        $unit_measure = isset($_POST['um']) ? wc_clean(wp_unslash($_POST['um'])) : '';

        if ($unit_measure === '') {
            $product->delete_meta_data('um');
            return;
        }

        if (!in_array($unit_measure, self::SUPPORTED_UNITS, true)) {
            return;
        }

        $product->update_meta_data('um', $unit_measure);
    }

    /**
     * Adds the variation's own unit measure to WooCommerce's variation payload.
     *
     * The product page initially renders the parent variable product, so the
     * selected variation's "um" value has to travel with the variation data.
     */
    public function add_variation_unit_measure_data(array $variation_data, WC_Product $product, WC_Product $variation): array
    {
        // Variable product pages need the selected variation's unit, not only the parent product's unit.
        $unit_measure = $this->get_direct_unit_measure($variation);

        if ($unit_measure === '') {
            $unit_measure = $this->get_display_unit_measure($variation);
        }

        $variation_data['bonetimber_unit_measure'] = $unit_measure;

        return $variation_data;
    }

    public function append_to_dynamic_price(): void
    {
        if (!is_product()) {
            return;
        }

        $product = wc_get_product(get_queried_object_id());

        if (!$product instanceof WC_Product) {
            return;
        }

        $is_variable_product = $product->is_type('variable');
        $unit_measure = $this->get_display_unit_measure($product);

        if ($unit_measure === '' && !$is_variable_product) {
            return;
        }
        ?>
        <script>
            (function() {
                var parentUnitMeasure = <?php echo wp_json_encode($unit_measure); ?>;
                var currentUnitMeasure = parentUnitMeasure;

                function getUnitMeasureText() {
                    return currentUnitMeasure ? '/' + currentUnitMeasure : '';
                }

                function appendUnitMeasure() {
                    var priceTargets = document.querySelectorAll(
                        '.pprice .jet-listing-dynamic-field__content, ' +
                        '.pprice .price, ' +
                        '.single_variation_wrap .woocommerce-variation-price .price'
                    );

                    priceTargets.forEach(function(priceTarget) {
                        var unitText = getUnitMeasureText();
                        var existingUnit = priceTarget.querySelector('.bonetimber-product-unit-measure');

                        if (!unitText) {
                            if (existingUnit) {
                                existingUnit.remove();
                            }

                            return;
                        }

                        if (existingUnit) {
                            if (existingUnit.textContent !== unitText) {
                                existingUnit.textContent = unitText;
                            }

                            return;
                        }

                        var unit = document.createElement('span');
                        unit.className = 'bonetimber-product-unit-measure';
                        unit.textContent = unitText;
                        priceTarget.appendChild(unit);
                    });
                }

                appendUnitMeasure();

                if (window.jQuery) {
                    // Keep the displayed suffix in sync with the actual selected variation.
                    jQuery(document.body).on('found_variation', 'form.variations_form', function(event, variation) {
                        currentUnitMeasure = variation && variation.bonetimber_unit_measure ? variation.bonetimber_unit_measure : parentUnitMeasure;
                        appendUnitMeasure();
                    });

                    jQuery(document.body).on('reset_data hide_variation', 'form.variations_form', function() {
                        currentUnitMeasure = parentUnitMeasure;
                        appendUnitMeasure();
                    });
                }

                document.querySelectorAll('.single_variation_wrap').forEach(function(variationWrap) {
                    new MutationObserver(appendUnitMeasure).observe(variationWrap, {
                        childList: true,
                        subtree: true
                    });
                });
            })();
        </script>
        <?php
    }

    public function append_cart_unit_measure_column(): void
    {
        if (!is_cart() || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $cart_unit_measures = [];

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = isset($cart_item['data']) ? $cart_item['data'] : null;

            if (!$product instanceof WC_Product) {
                continue;
            }

            $cart_unit_measures[$cart_item_key] = $this->get_display_unit_measure($product);
        }

        if (empty($cart_unit_measures)) {
            return;
        }
        ?>
        <style>
            .bonetimber-cart-unit-measure-heading,
            .bonetimber-cart-unit-measure-cell {
                white-space: nowrap;
				
            }

            .bonetimber-cart-unit-measure-cell {
                font-weight: 600;
                color: #17262d;
            }
        </style>
        <script>
            (function() {
                var unitMeasures = <?php echo wp_json_encode($cart_unit_measures); ?>;
                var scheduled = false;

                function getTable() {
                    var tables = document.querySelectorAll('table');

                    for (var i = 0; i < tables.length; i++) {
                        var headings = tables[i].querySelectorAll('thead th');
                        var labels = Array.prototype.map.call(headings, function(heading) {
                            return heading.textContent.trim().toLowerCase();
                        });

                        if (
                            labels.indexOf('product title') !== -1 &&
                            labels.indexOf('price') !== -1 &&
                            labels.indexOf('total') !== -1
                        ) {
                            return tables[i];
                        }
                    }

                    return null;
                }

                function getCartItemKey(row) {
                    var keyedElement = row.querySelector('[data-cart_item_key]');
                    var removeLink;
                    var url;

                    if (keyedElement && keyedElement.getAttribute('data-cart_item_key')) {
                        return keyedElement.getAttribute('data-cart_item_key');
                    }

                    removeLink = row.querySelector('a[href*="remove_item="]');

                    if (!removeLink) {
                        return '';
                    }

                    try {
                        url = new URL(removeLink.href, window.location.href);
                        return url.searchParams.get('remove_item') || '';
                    } catch (error) {
                        return '';
                    }
                }

                function appendColumn() {
                    var table = getTable();
                    var headerRow;
                    var headings;
                    var quantityHeading;
                    var unitHeading;
                    var unitColumnIndex;
                    var rows;
                    var fallbackKeys = Object.keys(unitMeasures);

                    if (!table) {
                        return;
                    }

                    headerRow = table.querySelector('thead tr');

                    if (!headerRow) {
                        return;
                    }

                    unitHeading = headerRow.querySelector('.bonetimber-cart-unit-measure-heading');

                    if (!unitHeading) {
                        headings = headerRow.querySelectorAll('th');

                        for (var i = 0; i < headings.length; i++) {
                            if (headings[i].textContent.trim().toLowerCase() === 'quantity') {
                                quantityHeading = headings[i];
                                break;
                            }
                        }

                        if (!quantityHeading) {
                            return;
                        }

                        unitHeading = document.createElement('th');
                        unitHeading.className = 'bonetimber-cart-unit-measure-heading';
                        unitHeading.textContent = 'Unit of Measure';
                        quantityHeading.insertAdjacentElement('afterend', unitHeading);
                    }

                    unitColumnIndex = Array.prototype.indexOf.call(headerRow.children, unitHeading);
                    rows = table.querySelectorAll('tbody tr');

                    rows.forEach(function(row, rowIndex) {
                        var itemKey = getCartItemKey(row) || fallbackKeys[rowIndex] || '';
                        var unitMeasure = unitMeasures[itemKey] || '';
                        var cell = row.querySelector('.bonetimber-cart-unit-measure-cell');

                        if (!itemKey && rowIndex >= fallbackKeys.length) {
                            return;
                        }

                        if (!cell) {
                            cell = document.createElement('td');
                            cell.className = 'bonetimber-cart-unit-measure-cell';
                            cell.setAttribute('data-title', 'Unit of Measure');
                            row.insertBefore(cell, row.children[unitColumnIndex] || null);
                        }

                        if (cell.textContent !== unitMeasure) {
                            cell.textContent = unitMeasure;
                        }
                    });

                    table.querySelectorAll('tbody tr, tfoot tr').forEach(function(row) {
                        var spanningCell;

                        if (getCartItemKey(row)) {
                            return;
                        }

                        spanningCell = row.querySelector('td[colspan]');

                        if (spanningCell && spanningCell.colSpan !== headerRow.children.length) {
                            spanningCell.colSpan = headerRow.children.length;
                        }
                    });
                }

                function scheduleColumnUpdate() {
                    if (scheduled) {
                        return;
                    }

                    scheduled = true;

                    window.requestAnimationFrame(function() {
                        scheduled = false;
                        appendColumn();
                    });
                }

                appendColumn();

                new MutationObserver(scheduleColumnUpdate).observe(document.body, {
                    childList: true,
                    subtree: true
                });
            })();
        </script>
        <?php
    }

    private function get_display_unit_measure(WC_Product $product): string
    {
        $unit_measure = $this->get_direct_unit_measure($product);

        if ($unit_measure === '' && $product->is_type('variation')) {
            $parent_product = wc_get_product($product->get_parent_id());

            if ($parent_product instanceof WC_Product) {
                $unit_measure = $this->get_display_unit_measure($parent_product);
            }
        }

        if ($unit_measure === '' && $product->is_type('variable')) {
            $variation_units = [];

            foreach ($product->get_children() as $variation_id) {
                $variation_unit_measure = trim((string) get_post_meta($variation_id, 'um', true));

                if (in_array($variation_unit_measure, self::SUPPORTED_UNITS, true)) {
                    $variation_units[$variation_unit_measure] = true;
                }
            }

            if (count($variation_units) === 1) {
                $unit_measure = (string) array_key_first($variation_units);
            }
        }

        if (!in_array($unit_measure, self::SUPPORTED_UNITS, true)) {
            return '';
        }

        return $unit_measure;
    }

    /**
     * Returns only the product's own "um" value, without parent or child fallback.
     */
    private function get_direct_unit_measure(WC_Product $product): string
    {
        $unit_measure = trim((string) get_post_meta($product->get_id(), 'um', true));

        if (!in_array($unit_measure, self::SUPPORTED_UNITS, true)) {
            return '';
        }

        return $unit_measure;
    }

    /**
     * Builds the select options used by the product edit screen's "um" manager.
     */
    private function get_supported_unit_options(): array
    {
        $options = [
            '' => __('No unit measure', 'bonetimber'),
        ];

        foreach (self::SUPPORTED_UNITS as $unit_measure) {
            $options[$unit_measure] = $unit_measure;
        }

        return $options;
    }
}

new Bonetimber_Product_Unit_Measure();
