document.addEventListener("DOMContentLoaded", function () {
  const tabsBlocks = document.querySelectorAll(".progressus-tabs");

  tabsBlocks.forEach(function (tabsBlock) {
    const tabHeaders = tabsBlock.querySelectorAll(".progressus-tab-header");
    const tabContents = tabsBlock.querySelectorAll(".progressus-tab-content");

    // Store original styles for each tab header
    const originalStyles = [];
    tabHeaders.forEach(function (header, index) {
      originalStyles[index] = {
        style: header.getAttribute('style') || '',
        isActive: header.classList.contains('active')
      };
    });

    // Get initial active tab from data attribute
    const initialActiveTab = parseInt(tabsBlock.dataset.activeTab) || 0;

    // Set initial active state
    setActiveTab(initialActiveTab);

    // Add click event listeners to tab headers
    tabHeaders.forEach(function (header, index) {
      header.addEventListener("click", function () {
        setActiveTab(index);
      });

      // Add keyboard navigation
      header.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          setActiveTab(index);
        }

        // Arrow key navigation
        if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
          e.preventDefault();
          const currentIndex = Array.from(tabHeaders).indexOf(
            document.activeElement
          );
          let nextIndex;

          if (e.key === "ArrowLeft") {
            nextIndex =
              currentIndex > 0 ? currentIndex - 1 : tabHeaders.length - 1;
          } else {
            nextIndex =
              currentIndex < tabHeaders.length - 1 ? currentIndex + 1 : 0;
          }

          tabHeaders[nextIndex].focus();
          setActiveTab(nextIndex);
        }
      });

      // Make tab headers focusable
      header.setAttribute("tabindex", "0");
      header.setAttribute("role", "tab");
      header.setAttribute(
        "aria-selected",
        index === initialActiveTab ? "true" : "false"
      );
    });

    // Set ARIA attributes for accessibility
    tabsBlock.setAttribute("role", "tablist");
    tabContents.forEach(function (content, index) {
      content.setAttribute("role", "tabpanel");
      content.setAttribute("aria-labelledby", "tab-" + index);
      content.setAttribute("id", "tabpanel-" + index);
    });

    function setActiveTab(activeIndex) {
      // Remove active class from all headers and contents
      tabHeaders.forEach(function (header, index) {
        header.classList.toggle("active", index === activeIndex);
        header.setAttribute(
          "aria-selected",
          index === activeIndex ? "true" : "false"
        );
        
        // Restore original styles based on whether this tab should be active
        if (index === activeIndex) {
          // Apply the style that was originally on the active tab
          const activeOriginalStyle = originalStyles.find(style => style.isActive);
          if (activeOriginalStyle) {
            header.setAttribute('style', activeOriginalStyle.style);
          }
        } else {
          // Apply the style that was originally on inactive tabs
          const inactiveOriginalStyle = originalStyles.find(style => !style.isActive);
          if (inactiveOriginalStyle) {
            header.setAttribute('style', inactiveOriginalStyle.style);
          }
        }
      });

      tabContents.forEach(function (content, index) {
        content.classList.toggle("active", index === activeIndex);
        content.style.display = index === activeIndex ? "block" : "none";
      });

      // Update the data attribute
      tabsBlock.dataset.activeTab = activeIndex;

      // Focus the active tab header for keyboard users
      if (
        document.activeElement &&
        Array.from( tabHeaders ).includes( document.activeElement )
      ) {
        tabHeaders[activeIndex].focus();
      }

      // Trigger custom event for other scripts
      const event = new CustomEvent("progressusTabChanged", {
        detail: {
          activeIndex: activeIndex,
          tabsBlock: tabsBlock,
          activeHeader: tabHeaders[activeIndex],
          activeContent: tabContents[activeIndex],
        },
      });
      tabsBlock.dispatchEvent(event);
    }
  });
});

// Export for use in other scripts if needed
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    initializeTabs: function (selector) {
      const tabsBlocks = document.querySelectorAll(
        selector || ".progressus-tabs"
      );
      // Re-run initialization logic here if needed
    },
  };
}
