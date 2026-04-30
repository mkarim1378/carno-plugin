<?php
// ============================================================================
// رابط کاربری - فاوآیکون داینامیک، لینک‌های UI، و اسکریپت‌های ظاهری
// ============================================================================

/**
 * فاوآیکون داینامیک بر اساس حالت روشن/تاریک مرورگر
 */
add_action('init', function() {
    add_filter('site_icon_meta_tags', '__return_empty_array', 999);
    remove_action('wp_head', 'wp_site_icon', 99);
}, 999);

add_action('wp_head', 'carno_ultimate_favicon_switcher', 0);
add_action('admin_head', 'carno_ultimate_favicon_switcher', 0);

function carno_ultimate_favicon_switcher() {
    $white_logo = 'https://sepehralimohammadi.com/wp-content/uploads/2026/01/carno-logo-dark.webp';
    $dark_logo  = 'https://sepehralimohammadi.com/wp-content/uploads/2026/01/carno-logo-light.webp';
    $version    = '3.0.1';
    ?>
    <link rel="icon" id="carno-favicon" href="<?php echo $dark_logo; ?>?v=<?php echo $version; ?>" type="image/webp">
    <script>
    (function() {
        const whiteIcon = "<?php echo $white_logo; ?>?v=<?php echo $version; ?>";
        const darkIcon  = "<?php echo $dark_logo; ?>?v=<?php echo $version; ?>";
        const favElem   = document.getElementById('carno-favicon');

        function applyFavicon() {
            const isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (isDarkMode) {
                favElem.href = whiteIcon;
            } else {
                favElem.href = darkIcon;
            }
        }
        applyFavicon();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyFavicon);
    })();
    </script>
    <?php
}

// ============================================================================
// حذف پارامتر add-to-cart از URL بعد از افزودن محصول به سبد
function remove_add_to_cart_parameter_after_redirect() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            if (window.location.search.includes('add-to-cart')) {
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'remove_add_to_cart_parameter_after_redirect');

// ============================================================================
// نمایش داینامیک باکس CTA لندینگ دوره‌ها بعد از اسکرول (دسکتاپ)
add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function () {

  if (window.innerWidth <= 1024) return;

  var hero = document.getElementById("hero-section");
  var cta = document.querySelector(".floating-cta");

  if (!hero || !cta) return;

  var heroBottom = hero.offsetTop + hero.offsetHeight;

  window.addEventListener("scroll", function () {
    if (window.scrollY > heroBottom) {
      cta.classList.add("show");
    } else {
      cta.classList.remove("show");
    }
  });

});
</script>
<?php
});
