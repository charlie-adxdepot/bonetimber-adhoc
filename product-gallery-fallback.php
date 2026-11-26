<?php
/**
 * Fixes the WooCommerce single product gallery (JetWooBuilder's "Product
 * Gallery" widget) when its thumbnails and zoom stop responding.
 *
 * Elementor Pro's widget handler throws "JetSwiper is not a constructor"
 * while trying to initialize this gallery's Swiper instances (a Jet Woo
 * Product Gallery / Elementor Pro version mismatch), so neither the
 * thumbnail row nor the zoom trigger ever get wired up. This adds a
 * self-contained fallback that only runs when it detects Swiper never
 * actually initialized on the gallery, so it steps aside automatically
 * once the underlying plugin conflict is resolved.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Bonetimber_Product_Gallery_Fallback {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Load the fallback only on single product pages, where this gallery appears.
     */
    public function enqueue_assets() {
        if (! is_product()) {
            return;
        }

        $handle = 'bonetimber-product-gallery-fallback';

        wp_register_script($handle, false, [], '1.0.0', true);
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, $this->get_js());

        wp_register_style($handle, false, [], '1.0.0');
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, $this->get_css());
    }

    /**
     * Highlights whichever thumbnail is currently selected. Scoped to the
     * thumbs row only so it can't affect any other Swiper instance on the page.
     */
    private function get_css() {
        return '
            .jet-woo-swiper-gallery-thumbs .swiper-slide-thumb-active {
                outline: 2px solid #f2a93b;
                outline-offset: -2px;
            }
        ';
    }

    /**
     * Replicates just enough of the gallery's intended behaviour (swap the
     * featured image on thumbnail click, open the full-size image on zoom
     * click) using plain DOM APIs, bypassing the broken Swiper widgets
     * entirely.
     */
    private function get_js() {
        return '
            (function () {
                "use strict";

                function getSlides(container) {
                    var wrapper = container ? container.querySelector(".swiper-wrapper") : null;

                    if (!wrapper) {
                        return [];
                    }

                    return Array.prototype.filter.call(wrapper.children, function (el) {
                        return el.classList.contains("swiper-slide");
                    });
                }

                function initGallery(gallery) {
                    var mainContainer = gallery.querySelector(".jet-woo-product-gallery-slider");
                    var thumbsContainer = gallery.querySelector(".jet-woo-swiper-gallery-thumbs");

                    if (!mainContainer || !thumbsContainer) {
                        return;
                    }

                    // Real Swiper is working fine here; leave it alone.
                    if (
                        mainContainer.classList.contains("swiper-initialized") ||
                        mainContainer.classList.contains("swiper-container-initialized")
                    ) {
                        return;
                    }

                    var mainSlides = getSlides(mainContainer);
                    var thumbSlides = getSlides(thumbsContainer);

                    if (!mainSlides.length || mainSlides.length !== thumbSlides.length) {
                        return;
                    }

                    function showSlide(index) {
                        mainSlides.forEach(function (slide, i) {
                            slide.style.display = i === index ? "" : "none";
                        });

                        thumbSlides.forEach(function (slide, i) {
                            slide.classList.toggle("swiper-slide-thumb-active", i === index);
                        });
                    }

                    var featuredIndex = mainSlides.findIndex(function (slide) {
                        return slide.classList.contains("featured");
                    });

                    showSlide(featuredIndex === -1 ? 0 : featuredIndex);

                    thumbSlides.forEach(function (slide, index) {
                        slide.style.cursor = "pointer";
                        slide.addEventListener("click", function () {
                            showSlide(index);
                        });
                    });

                    mainSlides.forEach(function (slide) {
                        var trigger = slide.querySelector(".jet-woo-product-gallery__trigger");
                        var link = slide.querySelector(".jet-woo-product-gallery__image-link");

                        if (!trigger || !link) {
                            return;
                        }

                        [trigger, link].forEach(function (clickable) {
                            clickable.addEventListener("click", function (event) {
                                event.preventDefault();
                                openLightbox(link.getAttribute("href"));
                            });
                        });
                    });
                }

                var overlay = null;

                function closeLightbox() {
                    if (overlay) {
                        overlay.remove();
                        overlay = null;
                    }
                }

                function openLightbox(imageUrl) {
                    if (!imageUrl) {
                        return;
                    }

                    closeLightbox();

                    overlay = document.createElement("div");
                    overlay.setAttribute("data-bonetimber-gallery-lightbox", "");
                    overlay.style.cssText =
                        "position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,0.9);" +
                        "display:flex;align-items:center;justify-content:center;cursor:zoom-out;" +
                        "padding:40px;box-sizing:border-box;";

                    var img = document.createElement("img");
                    img.src = imageUrl;
                    img.style.cssText = "max-width:100%;max-height:100%;object-fit:contain;";
                    overlay.appendChild(img);

                    overlay.addEventListener("click", closeLightbox);
                    document.body.appendChild(overlay);
                }

                document.addEventListener("keydown", function (event) {
                    if (event.key === "Escape") {
                        closeLightbox();
                    }
                });

                function init() {
                    document.querySelectorAll(".jet-woo-product-gallery").forEach(initGallery);
                }

                if (document.readyState === "complete") {
                    init();
                } else {
                    window.addEventListener("load", init);
                }
            })();
        ';
    }
}

new Bonetimber_Product_Gallery_Fallback();
