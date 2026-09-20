"use strict";
/* Northstar theme toggle: explicit choice wins, else follows the OS setting. */
(function () {
  var root = document.documentElement;
  var KEY = "northstar-theme";
  function systemTheme() {
    return window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }
  function paint(theme) {
    var buttons = document.querySelectorAll("[data-theme-toggle]");
    for (var i = 0; i < buttons.length; i++) {
      var icon = buttons[i].querySelector("span");
      if (icon) icon.textContent = theme === "dark" ? "☀️" : "🌙";
      buttons[i].setAttribute(
        "aria-label",
        theme === "dark" ? "Switch to light mode" : "Switch to dark mode",
      );
    }
  }
  function apply(theme) {
    root.setAttribute("data-theme", theme);
    try {
      localStorage.setItem(KEY, theme);
    } catch (e) {
      /* private mode: theme simply resets next visit */
    }
    paint(theme);
  }
  var saved = null;
  try {
    saved = localStorage.getItem(KEY);
  } catch (e) {
    saved = null;
  }
  apply(saved === "dark" || saved === "light" ? saved : systemTheme());
  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-theme-toggle]");
    if (!button) return;
    event.preventDefault();
    apply(
      root.getAttribute("data-theme") === "dark" ? "light" : "dark",
    );
  });
})();
