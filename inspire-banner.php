<?php
/**
 * Displays a banner above the product grid on the Inspire Collection
 * category page and any of its child categories, including when the shop
 * page is filtered to that category via Elementor Pro's Loop Builder /
 * JetSmartFilters (?jsf=epro-loop-builder&tax=product_cat:...).
 *
 * The product grid on this site is rendered by an Elementor Loop Grid
 * widget rather than the classic WooCommerce shop loop template, so there
 * is no PHP hook that reliably fires directly above it. Checking/unchecking
 * JetSmartFilters' category checkboxes also re-renders the grid via AJAX
 * (no page reload), updating the URL's tax= param via history.replaceState
 * and firing a "jet-filter-content-rendered" event on document (confirmed
 * against plugins/jet-smart-filters/assets/js/public.js). So the banner is
 * printed once (hidden) at wp_body_open, and a script decides whether to
 * show/reposition or hide it every time that event fires, by reading the
 * current tax= param rather than relying on the page's initial PHP state.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Inspire_Banner
{
    private const CATEGORY_SLUG = 'inspire-collection';
    //private const BANNER_IMAGE_URL = 'https://bonetimber.com.au/wp-content/uploads/new_inspire_page_banner.png';
    private const BANNER_IMAGE_URL = 'https://bonetimber.com.au/wp-content/uploads/4-banner-1-scaled.png';
    private const BANNER_ALT = 'Inspire Collection';
    private const TAXONOMY = 'product_cat';

    public function __construct()
    {
        add_action('wp_body_open', [$this, 'render_banner']);
    }

    public function render_banner(): void
    {
        if (!is_shop() && !is_product_category()) {
            return;
        }

        $inspire_branch_ids = $this->get_inspire_collection_branch_ids();

        if (empty($inspire_branch_ids)) {
            return;
        }

        $initial_match = $this->is_inspire_collection_or_descendant();
        ?>
        <div class="bonetimber-inspire-banner" data-bonetimber-inspire-banner hidden>
            <img
                src="<?php echo esc_url(self::BANNER_IMAGE_URL); ?>"
                alt="<?php echo esc_attr(self::BANNER_ALT); ?>"
                style="display: block; width: 100%; height: auto; margin-bottom: 20px;"
            >
        </div>
        <script>
            (function () {
                "use strict";

                var inspireBranchIds = <?php echo wp_json_encode(array_values($inspire_branch_ids)); ?>;
                var initialMatch = <?php echo $initial_match ? 'true' : 'false'; ?>;

                function currentlyMatchesInspire() {
                    var taxParam = new URLSearchParams(window.location.search).get('tax');

                    if (!taxParam) {
                        return initialMatch;
                    }

                    if (taxParam.indexOf('product_cat:') !== 0) {
                        return false;
                    }

                    var requestedIds = taxParam.slice('product_cat:'.length).split(',').map(Number);

                    return requestedIds.some(function (id) {
                        return inspireBranchIds.indexOf(id) !== -1;
                    });
                }

                function commonAncestor(a, b) {
                    var ancestors = [];

                    for (var node = a; node; node = node.parentElement) {
                        ancestors.push(node);
                    }

                    for (var other = b; other; other = other.parentElement) {
                        if (ancestors.indexOf(other) !== -1) {
                            return other;
                        }
                    }

                    return null;
                }

                function childContaining(ancestor, descendant) {
                    var node = descendant;

                    while (node && node.parentElement !== ancestor) {
                        node = node.parentElement;
                    }

                    return node;
                }

                function placeBanner(banner) {
                    var grid = document.querySelector('.elementor-loop-container.elementor-grid, ul.products');
                    var sorting = document.querySelector('.elementor-widget-jet-smart-filters-sorting');
                    var target = grid;

                    if (grid && sorting) {
                        var ancestor = commonAncestor(grid, sorting);

                        if (ancestor) {
                            target = childContaining(ancestor, sorting) || grid;
                        }
                    }

                    if (!target || !target.parentElement) {
                        return;
                    }

                    target.parentElement.insertBefore(banner, target);
                    banner.hidden = false;
                }

                function evaluate() {
                    var banner = document.querySelector('[data-bonetimber-inspire-banner]');

                    if (!banner) {
                        return;
                    }

                    if (currentlyMatchesInspire()) {
                        placeBanner(banner);
                    } else {
                        banner.hidden = true;
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', evaluate);
                } else {
                    evaluate();
                }

                if (window.jQuery) {
                    window.jQuery(document).on('jet-filter-content-rendered', evaluate);
                }
            })();
        </script>
        <?php
    }

    /**
     * The Inspire Collection category's own term ID plus every descendant
     * category ID, no matter how deeply nested.
     *
     * @return int[]
     */
    private function get_inspire_collection_branch_ids(): array
    {
        $inspire_term = get_term_by('slug', self::CATEGORY_SLUG, self::TAXONOMY);

        if (!$inspire_term instanceof WP_Term) {
            return [];
        }

        $descendant_ids = get_term_children($inspire_term->term_id, self::TAXONOMY);

        return array_merge(
            [$inspire_term->term_id],
            is_wp_error($descendant_ids) ? [] : $descendant_ids
        );
    }

    /**
     * True on the Inspire Collection category itself, or any category
     * nested underneath it, no matter how deep. Used only as the initial
     * server-rendered state; JetSmartFilters' AJAX category checkboxes are
     * handled client-side, see render_banner().
     */
    private function is_inspire_collection_or_descendant(): bool
    {
        if (!is_product_category()) {
            return false;
        }

        $viewed_term = get_queried_object();

        if (!$viewed_term instanceof WP_Term) {
            return false;
        }

        if ($viewed_term->slug === self::CATEGORY_SLUG) {
            return true;
        }

        $inspire_term = get_term_by('slug', self::CATEGORY_SLUG, self::TAXONOMY);

        if (!$inspire_term instanceof WP_Term) {
            return false;
        }

        return in_array($inspire_term->term_id, get_ancestors($viewed_term->term_id, self::TAXONOMY), true);
    }
}

new Bonetimber_Inspire_Banner();
