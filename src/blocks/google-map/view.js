document.addEventListener("DOMContentLoaded", function () {
  if (typeof window === "undefined" || !window.google || !window.google.maps)
    return;

  const wrappers = document.querySelectorAll(".wp-block-progressus-google-map");
  wrappers.forEach((wrapper) => {
    const locJson = wrapper.getAttribute("data-location");
    if (!locJson) return;

    let location = null;
    try {
      location = JSON.parse(locJson);
    } catch (e) {
      location = null;
    }

    const mapType = wrapper.getAttribute("data-map-type") || "roadmap";
    const zoom = parseInt(wrapper.getAttribute("data-zoom") || "14", 10) || 14;
    const height =
      parseInt(wrapper.getAttribute("data-height") || "400", 10) || 400;

    // Create or find map container. If iframe exists, replace it.
    let mapContainer = wrapper.querySelector(".progressus-google-map__map");
    const existingIframe = wrapper.querySelector("iframe");
    if (!mapContainer) {
      mapContainer = document.createElement("div");
      mapContainer.className = "progressus-google-map__map";
      mapContainer.style.width = "100%";
      mapContainer.style.height = height + "px";
    }

    if (existingIframe) {
      existingIframe.parentNode.replaceChild(mapContainer, existingIframe);
    } else {
      // append if nothing to replace
      wrapper.appendChild(mapContainer);
    }

    try {
      const center =
        location && location.lat != null && location.lng != null
          ? { lat: parseFloat(location.lat), lng: parseFloat(location.lng) }
          : null;

      const map = new window.google.maps.Map(mapContainer, {
        zoom: zoom,
        mapTypeId: mapType,
      });

      if (center) {
        map.setCenter(center);
      } else if (location && location.address) {
        const geocoder = new window.google.maps.Geocoder();
        geocoder.geocode(
          { address: location.address },
          function (results, status) {
            if (status === "OK" && results && results[0]) {
              map.setCenter(results[0].geometry.location);
            }
          }
        );
      }

      if (center) {
        new window.google.maps.Marker({ map: map, position: center });
      }
    } catch (e) {
      // ignore individual map initialization errors
    }
  });
});
