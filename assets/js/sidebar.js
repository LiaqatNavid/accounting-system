document.addEventListener("DOMContentLoaded", function () {
    var buttons = document.querySelectorAll(".menu-btn");

    buttons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            var submenu = this.nextElementSibling;

            if (submenu.style.display === "block") {
                submenu.style.display = "none";
            } else {
                submenu.style.display = "block";
            }
        });
    });
});
