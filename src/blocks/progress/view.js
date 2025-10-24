document.addEventListener("DOMContentLoaded", () => {
  const progressBars = document.querySelectorAll(
    ".wp-block-progressus-progress"
  );

  if (!progressBars.length) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const bar = entry.target.querySelector(
            ".progressus-progress-bar-fill"
          );
          if (bar) {
            // Get the percentage from the width style
            const width = bar.style.width;
            // First set to 0
            bar.style.width = "0%";
            // Force a reflow
            bar.offsetWidth;
            // Then animate to the target width
            bar.style.width = width;
          }
          // Unobserve after animation
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.1,
    }
  );

  progressBars.forEach((bar) => observer.observe(bar));
});
