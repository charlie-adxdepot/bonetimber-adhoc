<?php
/**
 * Plugin Name: Bonetimber Enhancements
 * Description: Provides small ad hoc frontend updates, including promotional banner and product display enhancements.
 * Version: 1.0.0
 * Author: Charlie Macaraeg
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/unit-of-measure.php';
require_once __DIR__ . '/show-sub-total.php';
require_once __DIR__ . '/shop-filter-category.php';
require_once __DIR__ . '/checkout-message.php';
require_once __DIR__ . '/hide-draft-products.php';
//require_once __DIR__ . '/search-tag-match.php';
require_once __DIR__ . '/inspire-banner.php';
require_once __DIR__ . '/product-gallery-fallback.php';
require_once __DIR__ . '/fix-loop-pagination.php';
require_once __DIR__ . '/shipping-cost-notice.php';

final class Bonetimber_Adhoc_Banner
{
    private const HANDLE = 'bonetimber-adhoc-banner';
    private const BANNER_TEXT = 'More Timber & Building Supply Deals Coming Soon';
    private const BANNER_URL = 'https://bonetimber.com.au/shop/?jsf=epro-loop-builder&tax=product_cat:2234';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_body_open', [$this, 'render_banner']);
        //add_action('wp_footer', [$this, 'render_product_meta_details'], 100); //This is for testing purposes

        // This is a fix for a specific issue where variable products with the same price across variations were not showing the price on the product listing page. This filter forces the price to show in that case.
        add_filter('woocommerce_show_variation_price', [$this, 'show_price_variations'], 10, 3);
    }

    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE . '-font',
            'https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            self::HANDLE,
            plugin_dir_url(__FILE__) . 'assets/banner.css',
            [self::HANDLE . '-font'],
            '1.0.0'
        );

        wp_enqueue_script(
            self::HANDLE,
            plugin_dir_url(__FILE__) . 'assets/banner.js',
            [],
            '1.0.0',
            true
        );
    }

    public function render_banner(): void
    {
        if (is_admin()) {
            return;
        }
        if(isset($_GET['tax']) && $_GET['tax'] === 'product_cat:2234') {
        ?>
            <div class="bonetimber-adhoc-banner" data-bonetimber-banner>
                <a class="bonetimber-adhoc-banner__link" href="<?php echo esc_url(self::BANNER_URL); ?>">
                    <div class="bonetimber-adhoc-banner__inner">
                        <span class="bonetimber-adhoc-banner__text"><?php echo esc_html(self::BANNER_TEXT); ?></span>
                    </div>
                </a>
            </div>
        <?php
        }
    }
    
    public function show_price_variations($show, $product, $variation): bool
    {
        if (!$product || !is_a($product, 'WC_Product_Variable')) {
            return (bool) $show;
        }

        $min_price = $product->get_variation_price('min', true);
        $max_price = $product->get_variation_price('max', true);
        $min_regular_price = $product->get_variation_regular_price('min', true);
        $max_regular_price = $product->get_variation_regular_price('max', true);
        $min_sale_price = $product->get_variation_sale_price('min', true);
        $max_sale_price = $product->get_variation_sale_price('max', true);

        $has_same_variation_prices =
            $min_price !== '' &&
            $min_price === $max_price &&
            $min_regular_price === $max_regular_price &&
            $min_sale_price === $max_sale_price;

        if ($has_same_variation_prices) {
            return true;
        }

        return (bool) $show;

    }

    public function render_product_meta_details(): void
    {
        if (
            !is_product() ||
            !isset($_GET['details']) ||
            sanitize_text_field(wp_unslash($_GET['details'])) !== '1'
        ) {
            return;
        }

        $product = wc_get_product(get_queried_object_id());

        if (!$product instanceof WC_Product) {
            return;
        }

        $product_meta = get_post_meta($product->get_id());

        if (empty($product_meta)) {
            echo '<section class="bonetimber-product-meta-details"><h2>Product Meta Data</h2><p>No product meta data found.</p></section>';
            return;
        }
        ?>
        <section class="bonetimber-product-meta-details">
            <h2>Product Meta Data</h2>
            <table class="shop_attributes">
                <tbody>
                    <?php foreach ($product_meta as $meta_key => $meta_values) : ?>
                        <tr>
                            <th><?php echo esc_html($meta_key); ?></th>
                            <td>
                                <?php
                                foreach ((array) $meta_values as $meta_value) {
                                    $meta_value = maybe_unserialize($meta_value);
                                    $display_value = is_scalar($meta_value) || $meta_value === null
                                        ? (string) $meta_value
                                        : wp_json_encode($meta_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                                    echo '<pre style="white-space: pre-wrap; margin: 0 0 0.75rem;">' . esc_html((string) $display_value) . '</pre>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
    }
}

new Bonetimber_Adhoc_Banner();
