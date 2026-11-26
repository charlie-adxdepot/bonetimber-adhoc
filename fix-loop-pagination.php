<?php
/**
 * Fixes the Elementor Pro Loop Grid's pagination breaking after a
 * JetSmartFilters category selection.
 *
 * The Loop Grid widget's own query is configured as "Latest Products" with
 * no category set, so its pagination count is always calculated from the
 * full, unfiltered catalog. JetSmartFilters only ever swaps the *displayed*
 * product grid via AJAX (.elementor-loop-container); it has no way to tell
 * the widget its real result count changed, so the pagination keeps
 * showing the original page count, and its links (Elementor's native
 * e-page-* query var) don't correctly re-apply the active filter either,
 * landing the visitor on unrelated products instead of the next page.
 *
 * Three pieces:
 * 1. An AJAX endpoint that runs the same category filter the visitor has
 *    active and returns the real product total/page count.
 * 2. After every JetSmartFilters content update, calls that endpoint and
 *    rebuilds the pagination links to match the real total (or hides it
 *    entirely when everything fits on one page).
 * 3. Whenever a pagination link IS clicked, forces a real full-page
 *    navigation built from the current (already-filtered) URL plus the
 *    clicked page number, instead of letting Elementor's own AJAX
 *    pagination handle it - a genuine full page load has always rendered
 *    correctly in testing (identical to pasting the URL directly).
 */

if (! defined('ABSPATH')) {
    exit;
}

class Bonetimber_Fix_Loop_Pagination {

    /**
     * Fallback "products per page" used until the browser has actually
     * seen a full page rendered. Matches this site's Loop Grid setting.
     */
    const DEFAULT_PER_PAGE = 24;

    const AJAX_ACTION = 'bonetimber_get_filtered_page_count';

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_get_filtered_page_count']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'ajax_get_filtered_page_count']);
    }

    /**
     * Load only where JetSmartFilters' category filtering and the Loop Grid appear.
     */
    public function enqueue_assets() {
        if (! is_shop() && ! is_product_category()) {
            return;
        }

        $handle = 'bonetimber-fix-loop-pagination';

        wp_register_script($handle, false, [], '1.0.0', true);
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, $this->get_js());
    }

    /**
     * Returns the real product total and page count for a given set of
     * category term IDs, matching what the shop grid actually displays
     * (published products, excluding anything hidden from the catalog).
     */
    public function ajax_get_filtered_page_count() {
        $raw_cats = isset($_GET['cats']) ? sanitize_text_field(wp_unslash($_GET['cats'])) : '';
        $term_ids = array_values(array_filter(array_map('absint', explode(',', $raw_cats))));

        $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : self::DEFAULT_PER_PAGE;
        $per_page = min(100, max(1, $per_page));

        $tax_query = [
            [
                'taxonomy' => 'product_visibility',
                'field' => 'name',
                'terms' => 'exclude-from-catalog',
                'operator' => 'NOT IN',
            ],
        ];

        if (! empty($term_ids)) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN',
            ];
        }

        $result = wc_get_products([
            'status' => 'publish',
            'limit' => $per_page,
            'page' => 1,
            'paginate' => true,
            'return' => 'ids',
            'tax_query' => $tax_query,
        ]);

        wp_send_json_success([
            'total' => (int) $result->total,
            'max_num_pages' => (int) $result->max_num_pages,
        ]);
    }

    private function get_js() {
        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        $action = esc_js(self::AJAX_ACTION);
        $default_per_page = (int) self::DEFAULT_PER_PAGE;

        return '
            (function () {
                "use strict";

                var ajaxUrl = "' . $ajax_url . '";
                var actionName = "' . $action . '";
                var perPageBaseline = ' . $default_per_page . ';
                var cachedPageParam = null;

                function getPageParamName(url) {
                    var found = null;

                    url.searchParams.forEach(function (value, key) {
                        if (!found && key.indexOf("e-page-") === 0) {
                            found = key;
                        }
                    });

                    return found;
                }

                /**
                 * Elementor omits the e-page-* param entirely for page 1
                 * (its links just point back to the plain URL), so the
                 * param name is not always available from the current URL
                 * alone. It is cached the first time it is seen (from the
                 * URL, or from any existing pagination link) and reused
                 * from then on.
                 */
                function getCachedPageParam() {
                    var fromUrl = getPageParamName(new URL(window.location.href));

                    if (fromUrl) {
                        cachedPageParam = fromUrl;
                        return cachedPageParam;
                    }

                    var anyLink = document.querySelector(".elementor-pagination a.page-numbers");

                    if (anyLink) {
                        var fromLink = getPageParamName(new URL(anyLink.href, window.location.href));

                        if (fromLink) {
                            cachedPageParam = fromLink;
                        }
                    }

                    return cachedPageParam;
                }

                function getCategoryIdsFromUrl() {
                    var taxParam = new URLSearchParams(window.location.search).get("tax");

                    if (!taxParam || taxParam.indexOf("product_cat:") !== 0) {
                        return [];
                    }

                    return taxParam
                        .slice("product_cat:".length)
                        .split(",")
                        .map(function (id) { return parseInt(id, 10); })
                        .filter(function (id) { return !!id; });
                }

                function getCurrentPageNumber(pageParam) {
                    var value = new URL(window.location.href).searchParams.get(pageParam);
                    var page = value ? parseInt(value, 10) : 1;

                    return page > 0 ? page : 1;
                }

                function buildPaginationMarkup(currentPage, maxPages, pageParam) {
                    var html = "";

                    for (var i = 1; i <= maxPages; i++) {
                        if (i === currentPage) {
                            html += \'<span aria-current="page" class="page-numbers current"><span class="elementor-screen-only">Page</span>\' + i + "</span>";
                            continue;
                        }

                        var url = new URL(window.location.href);

                        if (i === 1) {
                            url.searchParams.delete(pageParam);
                        } else {
                            url.searchParams.set(pageParam, i);
                        }

                        html += \'<a class="page-numbers" href="\' + url.toString() + \'"><span class="elementor-screen-only">Page</span>\' + i + "</a>";
                    }

                    return html;
                }

                /**
                 * JetSmartFilters AJAX responses can leave the previous
                 * pagination markup in the page instead of replacing it, so
                 * after enough filter changes there can be several stacked
                 * copies. Only the most recently rendered one (last in the
                 * DOM) is kept; earlier copies are removed outright.
                 */
                function dedupePaginations() {
                    var paginations = document.querySelectorAll(".elementor-pagination");

                    if (!paginations.length) {
                        return null;
                    }

                    var lastIndex = paginations.length - 1;

                    paginations.forEach(function (pagination, index) {
                        if (index !== lastIndex) {
                            pagination.remove();
                        }
                    });

                    return paginations[lastIndex];
                }

                function trackPerPageBaseline() {
                    var grid = document.querySelector(".elementor-loop-container");

                    if (!grid) {
                        return;
                    }

                    var count = grid.querySelectorAll(".product").length;

                    if (count > perPageBaseline) {
                        perPageBaseline = count;
                    }
                }

                function initialCapture() {
                    trackPerPageBaseline();
                    getCachedPageParam();
                }

                /**
                 * Rapid, successive filter changes (e.g. ticking several
                 * category checkboxes in a row) fire several of these in
                 * overlapping fashion. Network responses can arrive out of
                 * order, so an older requests response landing after a
                 * newer one would otherwise clobber the correct result with
                 * stale data. Only the most recently issued requests
                 * response is ever allowed to touch the DOM.
                 */
                var latestRequestId = 0;

                /**
                 * Something else on the page (most likely one of the other
                 * handlers bound to the same "jet-filter-content-rendered"
                 * event) intermittently resets the pagination back to its
                 * stale, unfiltered markup a moment after this applies the
                 * correct one - the exact culprit was not pinned down, but
                 * the symptom only ever showed up a short time after. Rather
                 * than a single write, the correct state is re-asserted a
                 * few times over the following second so it outlasts
                 * whatever else is touching the same element.
                 */
                function applyPaginationState(maxPages, pageParam) {
                    [0, 300, 800, 1500].forEach(function (delay) {
                        setTimeout(function () {
                            var pagination = dedupePaginations();

                            if (!pagination) {
                                return;
                            }

                            if (maxPages <= 1) {
                                pagination.hidden = true;
                                return;
                            }

                            var currentPage = getCurrentPageNumber(pageParam);

                            pagination.hidden = false;
                            pagination.innerHTML = buildPaginationMarkup(currentPage, maxPages, pageParam);
                        }, delay);
                    });
                }

                /**
                 * Asks the server for the real product total under the
                 * active category filter, then applies it via
                 * applyPaginationState() (or hides the pagination entirely
                 * when only one page is needed), instead of leaving
                 * Elementors stale, unfiltered page count in place.
                 */
                function refreshPaginationFromRealTotal() {
                    trackPerPageBaseline();

                    var pagination = dedupePaginations();

                    if (!pagination) {
                        return;
                    }

                    var pageParam = getCachedPageParam();

                    if (!pageParam) {
                        return;
                    }

                    var requestId = ++latestRequestId;

                    var params = new URLSearchParams();
                    params.set("action", actionName);
                    params.set("per_page", perPageBaseline);
                    params.set("cats", getCategoryIdsFromUrl().join(","));

                    fetch(ajaxUrl + "?" + params.toString(), { credentials: "same-origin" })
                        .then(function (response) { return response.json(); })
                        .then(function (json) {
                            if (requestId !== latestRequestId) {
                                return;
                            }

                            if (!json || !json.success) {
                                return;
                            }

                            applyPaginationState(json.data.max_num_pages, pageParam);
                        })
                        .catch(function () {
                            // Network hiccup - leave the pagination as-is rather than guessing.
                        });
                }

                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initialCapture);
                } else {
                    initialCapture();
                }

                if (window.jQuery) {
                    window.jQuery(document).on("jet-filter-content-rendered", refreshPaginationFromRealTotal);
                }

                /**
                 * The clicked links page number is always correct (it is
                 * just Elementors own e-page-* query var); what is wrong is
                 * that Elementors own click handling does not carry the
                 * active JetSmartFilters tax query through to that page.
                 * Rebuilding the target URL from the current, already
                 * correctly-filtered address bar sidesteps that entirely.
                 */
                function forceRealPageNavigation(event) {
                    var link = event.target.closest(".elementor-pagination a.page-numbers");

                    if (!link) {
                        return;
                    }

                    var linkUrl = new URL(link.href, window.location.href);
                    var currentUrl = new URL(window.location.href);
                    var pageParam = getPageParamName(currentUrl) || getPageParamName(linkUrl);

                    if (!pageParam) {
                        return;
                    }

                    var targetUrl = new URL(window.location.href);
                    var linkPageValue = linkUrl.searchParams.get(pageParam);

                    if (linkPageValue) {
                        targetUrl.searchParams.set(pageParam, linkPageValue);
                    } else {
                        targetUrl.searchParams.delete(pageParam);
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    window.location.href = targetUrl.toString();
                }

                document.addEventListener("click", forceRealPageNavigation, true);
            })();
        ';
    }
}

new Bonetimber_Fix_Loop_Pagination();
