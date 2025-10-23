document.addEventListener("DOMContentLoaded", function () {
  const counters = document.querySelectorAll(".wp-block-progressus-counter");

  const startCounter = (counter) => {
    const countValue = counter.querySelector(".counter-value");
    const startVal = parseInt(counter.dataset.start || 0);
    const endVal = parseInt(counter.dataset.end || 100);
    const duration = parseInt(counter.dataset.duration || 2000);

    let startTime = null;

    const animation = (currentTime) => {
      if (!startTime) startTime = currentTime;
      const timeElapsed = currentTime - startTime;
      const progress = Math.min(timeElapsed / duration, 1);

      const currentCount = Math.floor(
        startVal + (endVal - startVal) * progress
      );
      countValue.textContent = currentCount;

      if (progress < 1) {
        requestAnimationFrame(animation);
      }
    };

    requestAnimationFrame(animation);
  };

  // Start counter when element is in viewport
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        startCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  });

  counters.forEach((counter) => observer.observe(counter));
});
