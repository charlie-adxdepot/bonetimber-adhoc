<?php
/**
 * Forces draft (and other unpublished) products out of search results,
 * regardless of how the search query was built.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Hide_Draft_Products
{
    public function __construct()
    {
        add_filter('posts_where', [$this, 'exclude_unpublished_products_from_search'], 10, 2);
    }

    /**
     * Some search implementations (e.g. JetSmartFilters) build their own
     * WP_Query and can end up requesting products with a status other than
     * "publish". This appends a WHERE clause so unpublished products are
     * excluded from any front-end search, no matter how the query itself
     * set its post_status or post_type.
     *
     * Variations don't inherit their parent product's status, so a variable
     * product left in draft can still surface here through its (still
     * "publish") product_variation posts. Those are excluded too, by
     * checking their parent's status.
     */
    public function exclude_unpublished_products_from_search(string $where, WP_Query $query): string
    {
        global $wpdb;

        if (!$query->is_search() || current_user_can('edit_products')) {
            return $where;
        }

        $where .= " AND NOT ({$wpdb->posts}.post_type = 'product' AND {$wpdb->posts}.post_status != 'publish')";

        $where .= " AND NOT ({$wpdb->posts}.post_type = 'product_variation' AND EXISTS (
            SELECT 1 FROM {$wpdb->posts} AS bonetimber_variation_parent
            WHERE bonetimber_variation_parent.ID = {$wpdb->posts}.post_parent
            AND bonetimber_variation_parent.post_status != 'publish'
        ))";

        return $where;
    }
}

new Bonetimber_Hide_Draft_Products();
