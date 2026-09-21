"use strict";
/* Northstar front-site helpers: popup dialogs for student signup, recovery and enquiries. */
(function () {
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
