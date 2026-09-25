"use strict";
/* Future design system interactions: drawer, accordions, counters, reveal, filters, back-to-top. */
(function () {
    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---------- mobile drawer ---------- */
    var burger = document.querySelector("[data-drawer-open]");
    var drawer = document.getElementById("fDrawer");
    var scrim = document.querySelector("[data-drawer-scrim]");
    var lastFocus = null;
    function openDrawer() {
        if (!drawer) return;
        lastFocus = document.activeElement;
        drawer.classList.add("open");
        drawer.setAttribute("aria-hidden", "false");
        if (scrim) scrim.classList.add("show");
        document.body.style.overflow = "hidden";
        if (burger) burger.setAttribute("aria-expanded", "true");
        var c = drawer.querySelector("[data-drawer-close]");
        if (c) c.focus();
    }
    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove("open");
        drawer.setAttribute("aria-hidden", "true");
        if (scrim) scrim.classList.remove("show");
        document.body.style.overflow = "";
        if (burger) burger.setAttribute("aria-expanded", "false");
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    if (burger) burger.addEventListener("click", openDrawer);
    var closer = document.querySelector("[data-drawer-close]");
    if (closer) closer.addEventListener("click", closeDrawer);
    if (scrim) scrim.addEventListener("click", closeDrawer);
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && drawer && drawer.classList.contains("open")) closeDrawer();
    });
    if (drawer) drawer.addEventListener("click", function (e) {
        var a = e.target.closest("a");
        if (a && !a.classList.contains("f-acc-btn")) closeDrawer();
    });

    /* ---------- drawer accordions ---------- */
    document.querySelectorAll(".f-acc-btn[aria-expanded]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var open = btn.getAttribute("aria-expanded") === "true";
            btn.setAttribute("aria-expanded", open ? "false" : "true");
            var panel = document.getElementById(btn.getAttribute("aria-controls"));
            if (panel) panel.classList.toggle("open", !open);
        });
    });

    /* ---------- bottom nav active state ---------- */
    try {
        var page = document.body.getAttribute("data-page") || "home";
        var map = { home: "home", courses: "programs", course: "programs", notices: "notices", login: "account", "create-account": "account" };
        var key = map[page] || "";
        document.querySelectorAll(".f-bottomnav a[data-nav]").forEach(function (a) {
            if (a.getAttribute("data-nav") === key) a.setAttribute("aria-current", "page");
        });
    } catch (e) { /* decorative only */ }

    /* ---------- animated counters ---------- */
    function finalText(el) {
        var target = parseInt(el.getAttribute("data-count") || "0", 10);
        var suffix = el.getAttribute("data-suffix") || "";
        el.textContent = target.toLocaleString("en-IN") + suffix;
    }
    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduceMotion && "IntersectionObserver" in window) {
        var seen = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                seen.unobserve(el);
                var target = parseInt(el.getAttribute("data-count") || "0", 10);
                var suffix = el.getAttribute("data-suffix") || "";
                if (target <= 0) { el.textContent = "0" + suffix; return; }
                var start = null, dur = 1400;
                function frame(t) {
                    if (!start) start = t;
                    var p = Math.min((t - start) / dur, 1);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased).toLocaleString("en-IN") + suffix;
                    if (p < 1) requestAnimationFrame(frame);
                }
                requestAnimationFrame(frame);
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { seen.observe(el); });
    } else {
        counters.forEach(finalText);
    }

    /* ---------- reveal on scroll ---------- */
    var reveals = document.querySelectorAll(".f-reveal");
    if (reveals.length && !reduceMotion && "IntersectionObserver" in window) {
        var ro = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    ro.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -6% 0px" });
        reveals.forEach(function (el) { ro.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add("is-visible"); });
    }

    /* ---------- back to top ---------- */
    var back = document.querySelector("[data-backtop]");
    if (back) {
        window.addEventListener("scroll", function () {
            back.classList.toggle("show", window.scrollY > 600);
        }, { passive: true });
        back.addEventListener("click", function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
        });
    }

    /* ---------- notice center filters (client-side, no backend change) ---------- */
    var search = document.querySelector("[data-notice-search]");
    var list = document.querySelector("[data-notice-list]");
    var emptyMsg = document.querySelector("[data-notice-empty]");
    var activeCat = "all";
    function applyFilters() {
        if (!list) return;
        var q = search ? search.value.trim().toLowerCase() : "";
        var shown = 0;
        list.querySelectorAll(".f-notice").forEach(function (n) {
            var cat = n.getAttribute("data-cat") || "";
            var text = (n.textContent || "").toLowerCase();
            var ok = (activeCat === "all" || cat === activeCat) && (!q || text.indexOf(q) !== -1);
            n.style.display = ok ? "" : "none";
            if (ok) shown++;
        });
        if (emptyMsg) emptyMsg.style.display = shown ? "none" : "";
        var count = document.querySelector("[data-notice-count]");
        if (count) count.textContent = shown;
    }
    if (search) search.addEventListener("input", applyFilters);
    document.querySelectorAll("[data-cat-filter]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            activeCat = btn.getAttribute("data-cat-filter");
            document.querySelectorAll("[data-cat-filter]").forEach(function (b) {
                b.setAttribute("aria-pressed", b === btn ? "true" : "false");
            });
            applyFilters();
        });
    });

    /* ---------- button loading state for forms ---------- */
    document.addEventListener("submit", function (e) {
        var form = e.target;
        if (!form || !form.querySelector) return;
        var btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.setAttribute("aria-busy", "true");
            btn.classList.add("is-loading");
        }
    });
})();
