<?php
/**
 * Handles JetSmartFilters category filter adjustments on the shop page.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Bonetimber_Shop_Filter_Category {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Load custom CSS and JS only on the WooCommerce shop page.
     */
    public function enqueue_assets() {
        if (! is_shop()) {
            return;
        }

        $handle = 'bonetimber-shop-filter-category';

        wp_register_style($handle, false, [], '1.0.0');
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, $this->get_css());

        wp_register_script($handle, false, ['jquery'], '1.0.0', true);
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, $this->get_js());
    }

    /**
     * Hide parent category checkboxes and make them behave like labels only.
     */
    private function get_css() {
        return '
            /*
             * Hide checkbox UI for parent category rows only.
             * Parent categories are still visible as labels.
             */
            .woocommerce-shop .jet-checkboxes-list__row:has(+ .jet-list-tree__children) .jet-checkboxes-list__input,
            .woocommerce-shop .jet-checkboxes-list__row:has(+ .jet-list-tree__children) .jet-checkboxes-list__decorator {
                display: none !important;
            }

            .woocommerce-shop .jet-checkboxes-list__row:has(+ .jet-list-tree__children) .jet-checkboxes-list__label {
                pointer-events: none !important;
                cursor: default !important;
                font-weight: 600;
            }

            /*
             * Top-level categories with no sub-categories render as a plain
             * checkbox row with an empty ".jet-collapse-none" spacer (instead
             * of the real ".jet-collapse-icon" toggle parent rows get).
             * Hide the checkbox UI there too and draw a "+" in that spacer so
             * it looks the same as every other top-level category. The row
             * itself keeps working as a normal filter checkbox underneath, so
             * clicking it still selects the category and shows its products.
             */
            .woocommerce-shop .jet-list-tree.jet-list-collapsible > .jet-checkboxes-list__row.jet-filter-row:not(.jet-list-tree__parent) .jet-checkboxes-list__input,
            .woocommerce-shop .jet-list-tree.jet-list-collapsible > .jet-checkboxes-list__row.jet-filter-row:not(.jet-list-tree__parent) .jet-checkboxes-list__decorator {
                display: none !important;
            }

            .woocommerce-shop .jet-list-tree.jet-list-collapsible > .jet-checkboxes-list__row.jet-filter-row:not(.jet-list-tree__parent) .jet-checkboxes-list__label {
                font-weight: 600;
            }

            .woocommerce-shop .jet-list-tree.jet-list-collapsible > .jet-checkboxes-list__row.jet-filter-row:not(.jet-list-tree__parent) .jet-collapse-none {
                cursor: pointer;
            }

            .woocommerce-shop .jet-list-tree.jet-list-collapsible > .jet-checkboxes-list__row.jet-filter-row:not(.jet-list-tree__parent) .jet-collapse-none::before {
                content: "+";
                display: block;
                width: 100%;
                text-align: center;
                font-size: 18px;
                line-height: 20px;
                font-weight: 700;
                color: #000000;
            }
        ';
    }

    /**
     * Prevent parent categories from being selected/submitted by JetSmartFilters.
     */
    private function get_js() {
        return '
            (function ($) {
                "use strict";

                function disableParentCategoryCheckboxes() {
                    $(".woocommerce-shop .jet-checkboxes-list__row").each(function () {
                        var $row = $(this);
                        var $children = $row.next(".jet-list-tree__children");

                        if (!$children.length) {
                            return;
                        }

                        var $input = $row.find(".jet-checkboxes-list__input");

                        if (!$input.length) {
                            return;
                        }

                        /*
                         * Remove selected state from parent category.
                         */
                        $input.prop("checked", false);
                        $input.removeAttr("checked");

                        /*
                         * Disable parent category so JetSmartFilters cannot submit it.
                         */
                        $input.prop("disabled", true);

                        /*
                         * Remove active/checked visual classes if Jet added them.
                         */
                        $row.removeClass("jet-checkboxes-list__row--checked checked active");
                        $row.find(".jet-checkboxes-list__button").removeClass("jet-checkboxes-list__button--checked checked active");
                    });
                }

                /*
                 * Top-level categories with no sub-categories don\'t get
                 * JetSmartFilters\' own expand/collapse toggle (there\'s
                 * nothing to expand into), so their "+" spacer is inert by
                 * default. Wire it up to trigger the row\'s own checkbox
                 * instead, so clicking the "+" filters straight to that
                 * category\'s products.
                 */
                function enableLeafCategoryToggle() {
                    $(".woocommerce-shop .jet-list-tree.jet-list-collapsible").each(function () {
                        $(this)
                            .children(".jet-checkboxes-list__row.jet-filter-row")
                            .not(".jet-list-tree__parent")
                            .each(function () {
                                var $row = $(this);
                                var $toggle = $row.children(".jet-collapse-none");

                                if (!$toggle.length || $toggle.data("btLeafToggleBound")) {
                                    return;
                                }

                                $toggle.data("btLeafToggleBound", true);

                                $toggle.on("click", function () {
                                    $row.find(".jet-checkboxes-list__input").trigger("click");
                                });
                            });
                    });
                }

                $(document).ready(function () {
                    disableParentCategoryCheckboxes();
                    enableLeafCategoryToggle();

                    /*
                     * JetSmartFilters often re-renders filters after AJAX updates,
                     * so we run it again after changes.
                     */
                    $(document).on("jet-filter-content-rendered jet-smart-filters/inited jet-smart-filters/updated", function () {
                        disableParentCategoryCheckboxes();
                        enableLeafCategoryToggle();
                    });

                    /*
                     * Safety fallback for AJAX re-rendering.
                     */
                    $(document).ajaxComplete(function () {
                        disableParentCategoryCheckboxes();
                        enableLeafCategoryToggle();
                    });
                });

            })(jQuery);
        ';
    }
}

new Bonetimber_Shop_Filter_Category();