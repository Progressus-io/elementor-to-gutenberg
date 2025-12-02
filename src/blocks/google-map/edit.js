import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody,
  TextControl,
  RangeControl,
  SelectControl,
  __experimentalBoxControl as BoxControl,
} from "@wordpress/components";
import { Fragment, useState, useEffect, useRef } from "@wordpress/element";

const mapTypes = [
  { label: __("Roadmap", "elementor-to-gutenberg"), value: "roadmap" },
  { label: __("Satellite", "elementor-to-gutenberg"), value: "satellite" },
  { label: __("Hybrid", "elementor-to-gutenberg"), value: "hybrid" },
  { label: __("Terrain", "elementor-to-gutenberg"), value: "terrain" },
];

const Edit = ({ attributes, setAttributes }) => {
  const locationAttr = attributes.location || {};
  const addressAttr = attributes.address;
  const latAttr = attributes.lat;
  const lngAttr = attributes.lng;
  const { zoom, height, mapType } = attributes;

  // Location preference: use `location` attribute when available (Elementor-style)
  const locAddress =
    locationAttr && locationAttr.address
      ? locationAttr.address
      : addressAttr || "";
  const locLat =
    locationAttr && locationAttr.lat != null
      ? locationAttr.lat
      : latAttr != null
      ? latAttr
      : null;
  const locLng =
    locationAttr && locationAttr.lng != null
      ? locationAttr.lng
      : lngAttr != null
      ? lngAttr
      : null;

  const blockProps = useBlockProps();
  const mapContainerRef = useRef(null);
  const inputRef = useRef(null);
  const mapRef = useRef(null);
  const markerRef = useRef(null);
  const autocompleteRef = useRef(null);
  const debounceRef = useRef(null);

  const [query, setQuery] = useState(locAddress || "");
  const [suggestions, setSuggestions] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);
  // Helper to convert stored spacing values to CSS strings for BoxControl display.
  const valueToCss = (v) => {
    if (v === undefined || v === null || v === "") return "0px";
    if (typeof v === "number") return v + "px";
    if (typeof v === "string") {
      const trimmed = v.trim();
      // If it already ends with a unit, return as-is.
      if (/[a-z%]$/i.test(trimmed)) return trimmed;
      // If it's numeric-like, append px.
      if (!isNaN(parseFloat(trimmed))) return trimmed + "px";
      return trimmed;
    }
    return String(v);
  };
  const padding = attributes?.style?.spacing?.padding ||
    attributes.padding || {
      top: 0,
      right: 0,
      bottom: 0,
      left: 0,
    };
  const margin = attributes?.style?.spacing?.margin ||
    attributes.margin || {
      top: 0,
      right: 0,
      bottom: 0,
      left: 0,
    };
  useEffect(() => {
    setQuery(locAddress || "");
  }, [locAddress]);

  useEffect(() => {
    // If Google Places is available, skip the Nominatim fallback here to avoid
    // initializing autocomplete in the editor during build-time.
    if (
      typeof window !== "undefined" &&
      window.google &&
      window.google.maps &&
      window.google.maps.places
    ) {
      return;
    }

    if (!query || query.length < 3) {
      setSuggestions([]);
      setShowSuggestions(false);
      return;
    }

    setIsLoading(true);
    setShowSuggestions(true);

    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      const url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&q=${encodeURIComponent(
        query
      )}`;

      fetch(url, { headers: { Accept: "application/json" } })
        .then((res) => res.json())
        .then((data) => {
          setSuggestions(
            (data || []).map((item) => ({
              label: item.display_name,
              lat: item.lat,
              lon: item.lon,
            }))
          );
        })
        .catch(() => setSuggestions([]))
        .finally(() => setIsLoading(false));
    }, 300);

    return () => clearTimeout(debounceRef.current);
  }, [query]);

  const selectSuggestion = (s) => {
    const locationObj = {
      address: s.label,
      lat: parseFloat(s.lat),
      lng: parseFloat(s.lon),
    };
    // Set both `location` (Elementor-style) and legacy fields for compatibility
    setAttributes({
      location: locationObj,
      address: s.label,
      lat: locationObj.lat,
      lng: locationObj.lng,
    });
    setQuery(s.label);
    setSuggestions([]);
    setShowSuggestions(false);
  };

  const onAddressChange = (value) => {
    setQuery(value);
    // When user types a new address, reset coordinates to force selection
    setAttributes({
      location: { address: value, lat: null, lng: null },
      address: value,
      lat: null,
      lng: null,
    });
  };

  const previewStyle = {
    height: `${height}px`,
    background: "#f3f3f3",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    color: "#555",
    border: "1px solid #ddd",
  };

  // Initialize Google Maps (editor preview) when API is available.
  useEffect(() => {
    if (typeof window === "undefined" || !window.google || !window.google.maps)
      return;
    if (!mapContainerRef.current) return;

    // Create map
    try {
      const center =
        locLat !== null && locLng !== null
          ? { lat: parseFloat(locLat), lng: parseFloat(locLng) }
          : null;

      mapRef.current = new window.google.maps.Map(mapContainerRef.current, {
        zoom: zoom || 14,
        mapTypeId: mapType || "roadmap",
      });

      if (center) {
        mapRef.current.setCenter(center);
      } else if (locAddress) {
        // Geocode address to center map in the editor preview
        try {
          const geocoder = new window.google.maps.Geocoder();
          geocoder.geocode({ address: locAddress }, (results, status) => {
            if (status === "OK" && results && results[0]) {
              mapRef.current.setCenter(results[0].geometry.location);
            }
          });
        } catch (e) {
          // ignore geocode failures in editor
        }
      }

    } catch (e) {
      // ignore initialization failures in editor
    }

    return () => {
      try {
        if (markerRef.current) {
          markerRef.current = null;
        }
      } catch (e) {}
      try {
        if (mapRef.current) {
          mapRef.current = null;
        }
      } catch (e) {}
    };
  }, [locLat, locLng, locAddress, zoom, mapType]);

  // Initialize Places Autocomplete in the editor when available.
  useEffect(() => {
    if (
      typeof window === "undefined" ||
      !window.google ||
      !window.google.maps ||
      !window.google.maps.places
    )
      return;
    if (!inputRef.current) return;

    try {
      autocompleteRef.current = new window.google.maps.places.Autocomplete(
        inputRef.current
      );
      autocompleteRef.current.setFields(["formatted_address", "geometry"]);
      const listener = () => {
        const place = autocompleteRef.current.getPlace();
        if (!place) return;
        const lat =
          place.geometry && place.geometry.location
            ? place.geometry.location.lat()
            : null;
        const lng =
          place.geometry && place.geometry.location
            ? place.geometry.location.lng()
            : null;
        const address = place.formatted_address || inputRef.current.value || "";
        const locationObj = { address, lat, lng };
        setAttributes({
          location: locationObj,
          address: address,
          lat: lat,
          lng: lng,
        });
        setQuery(address);
      };
      autocompleteRef.current.addListener("place_changed", listener);

      return () => {
        try {
          window.google.maps.event.clearInstanceListeners(
            autocompleteRef.current
          );
        } catch (e) {}
        autocompleteRef.current = null;
      };
    } catch (e) {
      // ignore
    }
  }, []);

  // Prepare iframe src like save.js
  let src = "";
  if (locLat !== null && locLng !== null) {
    src = `https://maps.google.com/maps?q=${encodeURIComponent(
      locLat
    )},${encodeURIComponent(locLng)}&z=${encodeURIComponent(
      zoom
    )}&output=embed`;
  } else if (locAddress) {
    src = `https://maps.google.com/maps?q=${encodeURIComponent(
      locAddress
    )}&z=${encodeURIComponent(zoom)}&output=embed`;
  }

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody
          title={__("Map Settings", "elementor-to-gutenberg")}
          initialOpen={true}
        >
          <p style={{ marginTop: 0, marginBottom: 8 }}>
            {__(
              "Set your Google Maps API Key in the plugin's Integrations Settings page.",
              "elementor-to-gutenberg"
            )}{" "}
            <a
              href="/wp-admin/admin.php?page=gutenberg-settings"
              target="_blank"
              rel="noopener noreferrer"
            >
              {__("Open Settings", "elementor-to-gutenberg")}
            </a>{" "}
            {__("Create your key", "elementor-to-gutenberg")}{" "}
            <a
              href="https://developers.google.com/maps/documentation/embed/get-api-key"
              target="_blank"
              rel="noopener noreferrer"
            >
              {__("here.", "elementor-to-gutenberg")}
            </a>
          </p>
          <div style={{ marginBottom: 8 }}>
            <label className="components-base-control__label">
              {__("Address", "elementor-to-gutenberg")}
            </label>
            <input
              type="text"
              ref={inputRef}
              className="components-text-control__input"
              value={query}
              onChange={(e) => onAddressChange(e.target.value)}
              onFocus={() => {
                if (suggestions.length) setShowSuggestions(true);
              }}
              onBlur={() => setTimeout(() => setShowSuggestions(false), 150)}
              placeholder={__(
                "Start typing an address...",
                "elementor-to-gutenberg"
              )}
              style={{ width: "100%" }}
            />

            {showSuggestions && (
              <div
                style={{
                  border: "1px solid #ddd",
                  background: "#fff",
                  maxHeight: 200,
                  overflowY: "auto",
                  marginTop: 4,
                  zIndex: 9999,
                }}
              >
                {isLoading && (
                  <div style={{ padding: 8 }}>
                    {__("Searching…", "elementor-to-gutenberg")}
                  </div>
                )}
                {!isLoading && suggestions.length === 0 && (
                  <div style={{ padding: 8 }}>
                    {__("No suggestions", "elementor-to-gutenberg")}
                  </div>
                )}
                {suggestions.map((s, i) => (
                  <button
                    key={i}
                    type="button"
                    className="components-button"
                    onMouseDown={(e) => {
                      // prevent blur
                      e.preventDefault();
                      selectSuggestion(s);
                    }}
                    style={{
                      display: "block",
                      width: "100%",
                      textAlign: "left",
                      padding: "8px 10px",
                      border: "none",
                      background: "transparent",
                    }}
                  >
                    {s.label}
                  </button>
                ))}
              </div>
            )}
          </div>

          <TextControl
            label={__("Latitude (optional)", "elementor-to-gutenberg")}
            value={locLat === null ? "" : String(locLat)}
            onChange={(value) =>
              setAttributes({ lat: value === "" ? null : parseFloat(value) })
            }
          />
          <TextControl
            label={__("Longitude (optional)", "elementor-to-gutenberg")}
            value={locLng === null ? "" : String(locLng)}
            onChange={(value) =>
              setAttributes({ lng: value === "" ? null : parseFloat(value) })
            }
          />
          <RangeControl
            label={__("Zoom", "elementor-to-gutenberg")}
            value={zoom}
            onChange={(value) => setAttributes({ zoom: value })}
            min={1}
            max={20}
          />
          <RangeControl
            label={__("Height (px)", "elementor-to-gutenberg")}
            value={height}
            onChange={(value) => setAttributes({ height: value })}
            min={100}
            max={1200}
          />
          {/* Show Marker removed */}
          <SelectControl
            label={__("Map Type", "elementor-to-gutenberg")}
            value={mapType}
            options={mapTypes}
            onChange={(value) => setAttributes({ mapType: value })}
          />
          <PanelBody
            title={__("Dimensions", "progressus-gutenberg")}
            initialOpen={false}
          >
            {/** Normalize values for BoxControl display: accept '2px' or numeric 2 */}
            <BoxControl
              label={__("Padding", "progressus-gutenberg")}
              values={{
                top: valueToCss(padding.top),
                right: valueToCss(padding.right),
                bottom: valueToCss(padding.bottom),
                left: valueToCss(padding.left),
              }}
              onChange={(value) => {
                const parsed = {
                  top: parseInt(value.top) || 0,
                  right: parseInt(value.right) || 0,
                  bottom: parseInt(value.bottom) || 0,
                  left: parseInt(value.left) || 0,
                };
                // keep legacy fields and canonical style.spacing in sync
                const newStyle = {
                  ...(attributes.style || {}),
                  spacing: {
                    ...(attributes.style?.spacing || {}),
                    ...(attributes.style?.spacing?.padding
                      ? { padding: parsed }
                      : { padding: parsed }),
                  },
                };
                setAttributes({
                  padding: parsed,
                  _padding: parsed,
                  style: newStyle,
                });
              }}
              __nextHasNoMarginBottom
            />

            <BoxControl
              label={__("Margin", "progressus-gutenberg")}
              values={{
                top: valueToCss(margin.top),
                right: valueToCss(margin.right),
                bottom: valueToCss(margin.bottom),
                left: valueToCss(margin.left),
              }}
              onChange={(value) => {
                const parsed = {
                  top: parseInt(value.top) || 0,
                  right: parseInt(value.right) || 0,
                  bottom: parseInt(value.bottom) || 0,
                  left: parseInt(value.left) || 0,
                };
                const newStyle = {
                  ...(attributes.style || {}),
                  spacing: {
                    ...(attributes.style?.spacing || {}),
                    ...(attributes.style?.spacing?.margin
                      ? { margin: parsed }
                      : { margin: parsed }),
                  },
                };
                // keep legacy fields and canonical style.spacing in sync
                setAttributes({
                  margin: parsed,
                  _margin: parsed,
                  style: newStyle,
                });
              }}
              __nextHasNoMarginBottom
            />
          </PanelBody>
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div style={previewStyle}>
          {typeof window !== "undefined" &&
          window.google &&
          window.google.maps ? (
            <div
              ref={mapContainerRef}
              style={{ width: "100%", height: "100%" }}
            />
          ) : src ? (
            <iframe
              src={src}
              style={{ width: "100%", height: "100%", border: 0 }}
              loading="lazy"
            />
          ) : (
            <div>
              {__(
                "Enter an address or coordinates to preview",
                "elementor-to-gutenberg"
              )}
            </div>
          )}
        </div>
      </div>
    </Fragment>
  );
};

export default Edit;
