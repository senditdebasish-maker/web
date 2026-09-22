"use strict";
/* Northstar front-site helpers: popup dialogs for student signup, recovery, enquiries and applicant tracking. */
(function () {
  var slider = document.querySelector("[data-hero-slider]");
  if (slider) {
    var slides = slider.querySelectorAll(".u-hero-slide");
    var dots = slider.querySelectorAll("[data-hero-dot]");
    var current = 0;
    var timer;
    function showSlide(index) {
      current = (index + slides.length) % slides.length;
      for (var i = 0; i < slides.length; i++) slides[i].classList.toggle("is-active", i === current);
      for (var j = 0; j < dots.length; j++) dots[j].classList.toggle("is-active", j === current);
    }
    function restart() { clearInterval(timer); timer = setInterval(function () { showSlide(current + 1); }, 7000); }
    slider.querySelector("[data-hero-prev]").addEventListener("click", function () { showSlide(current - 1); restart(); });
    slider.querySelector("[data-hero-next]").addEventListener("click", function () { showSlide(current + 1); restart(); });
    for (var k = 0; k < dots.length; k++) dots[k].addEventListener("click", function () { showSlide(Number(this.getAttribute("data-hero-dot"))); restart(); });
    slider.addEventListener("mouseenter", function () { clearInterval(timer); });
    slider.addEventListener("mouseleave", restart);
    restart();
  }
  var revealItems = document.querySelectorAll(".u-section, .u-side-card, .u-badge-card, .u-stats-band, .u-cta-band");
  if ("IntersectionObserver" in window) {
    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
    revealItems.forEach(function (item) { item.classList.add("reveal-on-scroll"); revealObserver.observe(item); });
  }
  var loginEmail = document.querySelector("[data-login-email]");
  if (loginEmail) {
    var navigation = window.performance && performance.getEntriesByType ? performance.getEntriesByType("navigation")[0] : null;
    if ((navigation && navigation.type === "reload") || document.referrer === window.location.href) loginEmail.value = "";
  }
  function close(modal) {
    modal.removeAttribute("data-open");
  }
  document.addEventListener("click", function (event) {
    var open = event.target.closest("[data-modal-open]");
    if (open) {
      var modal = document.getElementById(open.getAttribute("data-modal-open"));
      if (modal) {
        modal.setAttribute("data-open", "1");
        var field = modal.querySelector("input, select, textarea, button.u-btn");
        if (field) field.focus();
      }
      return;
    }
    if (event.target.closest("[data-modal-close]")) {
      var box = event.target.closest(".u-modal");
      if (box) close(box);
      return;
    }
    if (event.target.classList && event.target.classList.contains("u-modal")) {
      close(event.target);
    }
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      var modals = document.querySelectorAll('.u-modal[data-open="1"]');
      for (var i = 0; i < modals.length; i++) close(modals[i]);
    }
  });
})();
