<?php
/**
 * When a search term is also a product tag (e.g. "MDF"), WordPress' default
 * title/excerpt/content LIKE match also surfaces unrelated products that
 * merely mention the word in their description. This restricts that search
 * to products actually carrying the matching tag (and their variations),
 * leaving searches that aren't tag names to match as normal.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bonetimber_Search_Tag_Match
{
    private const TAXONOMY = 'product_tag';

    public function __construct()
    {
        add_filter('posts_search', [$this, 'restrict_search_to_tag_match'], 10, 2);
    }

    public function restrict_search_to_tag_match(string $search, WP_Query $query): string
    {
        global $wpdb;

        if ($search === '' || !$query->is_search()) {
            return $search;
        }

        $term_string = trim((string) $query->get('s'));

        if ($term_string === '') {
            return $search;
        }

        $tag = get_term_by('name', $term_string, self::TAXONOMY);

        if (!$tag instanceof WP_Term) {
            $tag = get_term_by('slug', sanitize_title($term_string), self::TAXONOMY);
        }

        if (!$tag instanceof WP_Term) {
            return $search;
        }

        $tagged_product_ids = get_objects_in_term($tag->term_id, self::TAXONOMY);

        if (is_wp_error($tagged_product_ids) || empty($tagged_product_ids)) {
            return ' AND 0=1 ';
        }

        $ids_list = implode(',', array_map('absint', $tagged_product_ids));

        return " AND (
            {$wpdb->posts}.ID IN ({$ids_list})
            OR ({$wpdb->posts}.post_type = 'product_variation' AND {$wpdb->posts}.post_parent IN ({$ids_list}))
        ) ";
    }
}

new Bonetimber_Search_Tag_Match();
