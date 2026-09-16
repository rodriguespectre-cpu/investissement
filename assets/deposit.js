document.addEventListener("DOMContentLoaded", () => {

    "use strict";

    const buttons = document.querySelectorAll(".deposit-btn");

    const loading = document.getElementById("depositLoading");
    const loadingTitle = document.getElementById("loadingTitle");
    const loadingMessage = document.getElementById("loadingMessage");
    const loadingProgress = document.getElementById("loadingProgress");
    const loadingAmount = document.getElementById("loadingAmount");

    const errorBox = document.getElementById("depositError");
    const errorMessage = document.getElementById("depositErrorMessage");
    const closeError = document.getElementById("closeDepositError");


    /*
    |--------------------------------------------------------------------------
    | ERREUR
    |--------------------------------------------------------------------------
    */

    function showError(message) {

        console.error("❌ DEPOT :", message);

        if (!errorBox) {
            alert(message);
            return;
        }

        if (errorMessage) {
            errorMessage.textContent =
                message || "Une erreur est survenue.";
        }

        errorBox.classList.add("show");
    }


    /*
    |--------------------------------------------------------------------------
    | FERMER ERREUR
    |--------------------------------------------------------------------------
    */

    if (closeError) {

        closeError.addEventListener("click", () => {

            errorBox.classList.remove("show");

        });

    }


    /*
    |--------------------------------------------------------------------------
    | LOADING
    |--------------------------------------------------------------------------
    */

    function showLoading(amount) {

        if (loadingAmount) {

            loadingAmount.textContent =
                "Montant sélectionné : " +
                Number(amount).toLocaleString("fr-FR") +
                " FCFA";

        }

        if (loadingTitle) {
            loadingTitle.textContent =
                "Préparation du paiement";
        }

        if (loadingMessage) {
            loadingMessage.textContent =
                "Connexion sécurisée à Chariow...";
        }

        if (loadingProgress) {
            loadingProgress.style.width = "15%";
        }

        if (loading) {

            loading.classList.add("active");

            loading.setAttribute(
                "aria-hidden",
                "false"
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE LOADING
    |--------------------------------------------------------------------------
    */

    function updateLoading(title, message, progress) {

        if (loadingTitle) {
            loadingTitle.textContent = title;
        }

        if (loadingMessage) {
            loadingMessage.textContent = message;
        }

        if (loadingProgress) {
            loadingProgress.style.width = progress + "%";
        }

    }


    /*
    |--------------------------------------------------------------------------
    | PAUSE
    |--------------------------------------------------------------------------
    */

    function sleep(ms) {

        return new Promise(resolve => {
            setTimeout(resolve, ms);
        });

    }


    /*
    |--------------------------------------------------------------------------
    | BOUTONS
    |--------------------------------------------------------------------------
    */

    buttons.forEach((button) => {

        button.addEventListener("click", async () => {

            if (button.disabled) {
                return;
            }


            const productId =
                button.dataset.productId;

            const amount =
                button.dataset.amount;


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if (!productId) {

                showError(
                    "L'identifiant du produit est manquant."
                );

                return;

            }


            if (!amount) {

                showError(
                    "Le montant du dépôt est manquant."
                );

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | DESACTIVER
            |--------------------------------------------------------------------------
            */

            buttons.forEach(btn => {
                btn.disabled = true;
            });


            showLoading(amount);


            try {

                /*
                |--------------------------------------------------------------------------
                | ETAPE 1
                |--------------------------------------------------------------------------
                */

                updateLoading(
                    "Préparation du paiement",
                    "Vérification de votre compte...",
                    25
                );

                await sleep(450);


                /*
                |--------------------------------------------------------------------------
                | ETAPE 2
                |--------------------------------------------------------------------------
                */

                updateLoading(
                    "Création de la commande",
                    "Préparation sécurisée de votre paiement...",
                    45
                );


                /*
                |--------------------------------------------------------------------------
                | FORM DATA
                |--------------------------------------------------------------------------
                */

                const formData = new FormData();

                formData.append(
                    "product_id",
                    productId
                );

                formData.append(
                    "amount",
                    amount
                );


                /*
                |--------------------------------------------------------------------------
                | DEBUG REQUETE
                |--------------------------------------------------------------------------
                */

                console.log(
                    "========== PAIEMENT =========="
                );

                console.log(
                    "Product ID :",
                    productId
                );

                console.log(
                    "Amount :",
                    amount
                );

                console.log(
                    "URL : ../../api/payment/create.php"
                );


                /*
                |--------------------------------------------------------------------------
                | APPEL PHP
                |--------------------------------------------------------------------------
                */

                const response = await fetch(
                    "../../api/payment/create.php",
                    {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",

                        headers: {
                            "Accept": "application/json"
                        }
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | REPONSE BRUTE
                |--------------------------------------------------------------------------
                */

                const raw =
                    await response.text();


                console.log(
                    "HTTP STATUS :",
                    response.status
                );

                console.log(
                    "REPONSE BRUTE :",
                    raw
                );


                /*
                |--------------------------------------------------------------------------
                | JSON
                |--------------------------------------------------------------------------
                */

                let data;

                try {

                    data = JSON.parse(raw);

                } catch (jsonError) {

                    console.error(
                        "❌ JSON INVALIDE"
                    );

                    console.error(
                        "Réponse reçue :",
                        raw
                    );

                    throw new Error(
                        "Le serveur a retourné une réponse JSON invalide."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | DEBUG JSON
                |--------------------------------------------------------------------------
                */

                console.log(
                    "REPONSE JSON :",
                    data
                );


                /*
                |--------------------------------------------------------------------------
                | ERREUR HTTP / API
                |--------------------------------------------------------------------------
                */

                if (!response.ok) {

                    throw new Error(
                        data.message ||
                        "Le serveur a retourné une erreur HTTP " +
                        response.status
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                if (data.success !== true) {

                    throw new Error(
                        data.message ||
                        "Chariow n'a pas accepté le paiement."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | REFERENCE
                |--------------------------------------------------------------------------
                */

                if (!data.reference) {

                    console.error(
                        "Réponse sans référence :",
                        data
                    );

                    throw new Error(
                        "La référence de transaction est manquante."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | PAYMENT URL
                |--------------------------------------------------------------------------
                */

                if (!data.payment_url) {

                    console.error(
                        "Réponse sans payment_url :",
                        data
                    );

                    throw new Error(
                        "L'URL de paiement est manquante."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | TOUT EST OK
                |--------------------------------------------------------------------------
                */

                console.log(
                    "✅ Paiement créé"
                );

                console.log(
                    "Référence :",
                    data.reference
                );

                console.log(
                    "Payment URL :",
                    data.payment_url
                );


                /*
                |--------------------------------------------------------------------------
                | ETAPE 3
                |--------------------------------------------------------------------------
                */

                updateLoading(
                    "Paiement sécurisé",
                    "Votre commande a été créée.",
                    70
                );

                await sleep(500);


                /*
                |--------------------------------------------------------------------------
                | ETAPE 4
                |--------------------------------------------------------------------------
                */

                updateLoading(
                    "Redirection",
                    "Ouverture de l'interface de paiement...",
                    90
                );

                await sleep(500);


                /*
                |--------------------------------------------------------------------------
                | REDIRECTION
                |--------------------------------------------------------------------------
                */

                window.location.href =
                    data.payment_url;


            } catch (error) {

                console.error(
                    "========== ERREUR PAIEMENT =========="
                );

                console.error(error);

                console.error(
                    "======================================"
                );


                if (loading) {

                    loading.classList.remove(
                        "active"
                    );

                    loading.setAttribute(
                        "aria-hidden",
                        "true"
                    );

                }


                buttons.forEach(btn => {
                    btn.disabled = false;
                });


                showError(
                    error.message ||
                    "Une erreur est survenue pendant la préparation du paiement."
                );

            }

        });

    });

});
