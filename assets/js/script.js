

document.addEventListener("DOMContentLoaded", () => {

    const slides = document.querySelectorAll(".investment-slide");
    const dots = document.querySelectorAll(".dot");

    const previous = document.querySelector(".carousel-btn.prev");
    const next = document.querySelector(".carousel-btn.next");

    let current = 0;
    let timer;


    function showSlide(index) {

        if (index >= slides.length) {
            index = 0;
        }

        if (index < 0) {
            index = slides.length - 1;
        }

        slides.forEach((slide, i) => {
            slide.classList.toggle(
                "active",
                i === index
            );
        });

        dots.forEach((dot, i) => {
            dot.classList.toggle(
                "active",
                i === index
            );
        });

        current = index;
    }


    function nextSlide() {
        showSlide(current + 1);
    }


    function previousSlide() {
        showSlide(current - 1);
    }


    function startCarousel() {

        clearInterval(timer);

        timer = setInterval(() => {
            nextSlide();
        }, 6000);

    }


    next.addEventListener("click", () => {
        nextSlide();
        startCarousel();
    });


    previous.addEventListener("click", () => {
        previousSlide();
        startCarousel();
    });


    dots.forEach((dot, index) => {

        dot.addEventListener("click", () => {

            showSlide(index);

            startCarousel();

        });

    });


    showSlide(0);

    startCarousel();

});



(function () {

    document.addEventListener('DOMContentLoaded', function () {

        const menu = document.querySelector('.spectre-start');
        const button = document.getElementById('spectreStartBtn');

        if (!menu || !button) {
            console.error('Menu Commencer introuvable');
            return;
        }

        button.addEventListener('click', function (event) {

            event.preventDefault();
            event.stopPropagation();

            menu.classList.toggle('open');

        });


        /*
        | Fermer quand on clique ailleurs
        */

        document.addEventListener('click', function (event) {

            if (!menu.contains(event.target)) {

                menu.classList.remove('open');

            }

        });


        /*
        | Fermer avec ESC
        */

        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape') {

                menu.classList.remove('open');

            }

        });

    });

})();

