"use strict";
const toggle = document.querySelector(".menu-toggle");
const sidebar = document.querySelector("#sidebar");
if (toggle && sidebar) {
  toggle.addEventListener("click", () => {
    const open = sidebar.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(open));
  });
  document.addEventListener("click", (event) => {
    if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
      sidebar.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      sidebar.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }
  });
}
// Prevent accidental duplicate clicks, while retaining normal HTML form submissions.
document.querySelectorAll('form[method="post"]').forEach((form) => {
  form.addEventListener("submit", () => {
    const button = form.querySelector(
      'button[type="submit"], button:not([type])',
    );
    if (button) {
      button.disabled = true;
      button.textContent = "Please wait…";
    }
  });
});
