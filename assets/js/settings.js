document.addEventListener("DOMContentLoaded", function () {

    const countrySelect = document.getElementById("country_code");
    const phonePrefix = document.getElementById("phonePrefix");
    const phoneInput = document.getElementById("phone");

    function updatePrefix() {

        if (!countrySelect || !phonePrefix) {
            return;
        }

        const option =
            countrySelect.options[countrySelect.selectedIndex];

        const prefix =
            option.getAttribute("data-prefix");

        if (prefix) {
            phonePrefix.textContent = prefix;
        }
    }

    if (countrySelect) {

        updatePrefix();

        countrySelect.addEventListener(
            "change",
            updatePrefix
        );
    }


    /*
    |--------------------------------------------------------------------------
    | NUMÉRO : uniquement chiffres
    |--------------------------------------------------------------------------
    */

    if (phoneInput) {

        phoneInput.addEventListener("input", function () {

            this.value =
                this.value.replace(/[^0-9]/g, "");

        });

    }


    /*
    |--------------------------------------------------------------------------
    | DISPARITION AUTOMATIQUE DES ALERTES
    |--------------------------------------------------------------------------
    */

    const alert = document.querySelector(".alert");

    if (alert) {

        setTimeout(function () {

            alert.style.opacity = "0";
            alert.style.transform = "translateY(-5px)";

            setTimeout(function () {
                alert.remove();
            }, 300);

        }, 5000);
    }

});
