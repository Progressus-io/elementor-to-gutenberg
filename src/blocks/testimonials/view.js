document.addEventListener("DOMContentLoaded", function () {
  const carousels = document.querySelectorAll(
    ".progressus-testimonials-carousel"
  );

  carousels.forEach((carousel) => {
    const slidesPerView = parseInt(carousel.dataset.slidesPerView) || 1;
    const spaceBetween = parseInt(carousel.dataset.spaceBetween) || 20;
    const arrowsSize = parseInt(carousel.dataset.arrowsSize) || 20;
    const arrowsColor = carousel.dataset.arrowsColor || "#000";
    const paginationGap = parseInt(carousel.dataset.paginationGap) || 10;
    const paginationSize = parseInt(carousel.dataset.paginationSize) || 10;
    const paginationColor = carousel.dataset.paginationColor || "#000";

    const swiper = carousel.querySelector(".swiper");

    if (typeof Swiper !== "undefined") {
      new Swiper(swiper, {
        slidesPerView: slidesPerView,
        spaceBetween: spaceBetween,
        loop: true,
        navigation: {
          nextEl: ".swiper-button-next",
          prevEl: ".swiper-button-prev",
        },
        pagination: {
          el: ".swiper-pagination",
          clickable: true,
        },
      });

      // Apply custom styles
      const prevButton = carousel.querySelector(".swiper-button-prev");
      const nextButton = carousel.querySelector(".swiper-button-next");
      const pagination = carousel.querySelector(".swiper-pagination");

      if (prevButton) {
        prevButton.style.fontSize = `${arrowsSize}px`;
        prevButton.style.color = arrowsColor;
      }
      if (nextButton) {
        nextButton.style.fontSize = `${arrowsSize}px`;
        nextButton.style.color = arrowsColor;
      }
      if (pagination) {
        pagination.style.marginTop = `${paginationGap}px`;
        const bullets = pagination.querySelectorAll(
          ".swiper-pagination-bullet"
        );
        bullets.forEach((bullet) => {
          bullet.style.width = `${paginationSize}px`;
          bullet.style.height = `${paginationSize}px`;
          bullet.style.backgroundColor = paginationColor;
        });
      }
    }
  });
});
