"use strict";
/* Nimita college homepage interactions: mobile menu, dropdowns, back-to-top, counters. */
(function () {

/* =========================================================
   MOBILE MENU
========================================================= */

var menuToggle = document.getElementById("menuToggle");
var navList = document.getElementById("navList");

if (menuToggle && navList) {
    menuToggle.addEventListener("click", function () {

        navList.classList.toggle("open");

        var icon = menuToggle.querySelector("i");

        if (navList.classList.contains("open")) {

            icon.classList.remove("fa-bars");
            icon.classList.add("fa-xmark");

        } else {

            icon.classList.remove("fa-xmark");
            icon.classList.add("fa-bars");

        }

    });
}


/* =========================================================
   MOBILE DROPDOWN
========================================================= */

document.querySelectorAll(".nav-item").forEach(function (item) {

    var link = item.querySelector(".nav-link");
    var dropdown = item.querySelector(".dropdown");

    if (dropdown) {

        link.addEventListener("click", function (e) {

            if (window.innerWidth <= 800) {

                e.preventDefault();

                item.classList.toggle("dropdown-open");

            }

        });

    }

});


/* =========================================================
   CLOSE MOBILE MENU AFTER CLICK
========================================================= */

document.querySelectorAll(".nav-list a").forEach(function (link) {

    link.addEventListener("click", function () {

        if (window.innerWidth <= 800) {

            var hasDropdown =
                this.parentElement.querySelector(".dropdown");

            if (!hasDropdown && navList && menuToggle) {

                navList.classList.remove("open");

                var icon =
                    menuToggle.querySelector("i");

                icon.classList.remove("fa-xmark");
                icon.classList.add("fa-bars");

            }

        }

    });

});


/* =========================================================
   BACK TO TOP
========================================================= */

var backTop = document.getElementById("backTop");

if (backTop) {
    window.addEventListener("scroll", function () {

        if (window.scrollY > 500) {

            backTop.classList.add("show");

        } else {

            backTop.classList.remove("show");

        }

    });


    backTop.addEventListener("click", function () {

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    });
}


/* =========================================================
   COUNTERS
========================================================= */

var counters =
    document.querySelectorAll(".counter");

var counterStarted = false;


function startCounters() {

    if (counterStarted) return;

    var stats =
        document.querySelector(".stats");

    if (!stats) return;

    var rect =
        stats.getBoundingClientRect();

    if (rect.top < window.innerHeight - 100) {

        counterStarted = true;

        counters.forEach(function (counter) {

            var target =
                parseInt(counter.dataset.target, 10);

            var suffix =
                counter.dataset.suffix || "+";

            var current = 0;

            var duration = 1600;

            var stepTime =
                Math.max(15, duration / target);

            var timer =
                setInterval(function () {

                    current++;

                    counter.textContent =
                        current + suffix;

                    if (current >= target) {

                        clearInterval(timer);

                    }

                }, stepTime);

        });

    }

}

window.addEventListener("scroll", startCounters);

startCounters();


/* =========================================================
   PREVENT EMPTY # LINKS FROM JUMPING
========================================================= */

document.querySelectorAll('a[href="#"]').forEach(function (link) {

    link.addEventListener("click", function (e) {

        e.preventDefault();

    });

});

})();
