document.addEventListener("DOMContentLoaded", function () {
  const banner = document.querySelector("[data-bonetimber-banner]");
  if (!banner) {
    return;
  }

  const header = document.querySelector(
    "#masthead, .site-header, .elementor-location-header, header"
  );

  if (header && header.parentNode) {
    header.insertAdjacentElement("afterend", banner);
  }
});
