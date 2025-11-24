import { useBlockProps } from "@wordpress/block-editor";

const Save = ({ attributes }) => {
  const { location, address, lat, lng, zoom, height } = attributes;

  // Prefer `location` attribute when present (Elementor-style)
  const locAddress =
    location && location.address ? location.address : address || "";
  const locLat =
    location && location.lat != null ? location.lat : lat != null ? lat : null;
  const locLng =
    location && location.lng != null ? location.lng : lng != null ? lng : null;

  const blockProps = useBlockProps.save();

  // Prefer coordinates when provided, otherwise fall back to address.
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

  const iframeStyle = {
    width: "100%",
    height: `${height}px`,
    border: "0",
  };

  // Build wrapper style from _margin and _padding attributes (Elementor-like shape)
  const wrapperStyle = {};
  if (attributes._margin) {
    const m = attributes._margin;
    if (m.unit) {
      const unit = m.unit || "px";
      const top = m.top || "0";
      const right = m.right || "0";
      const bottom = m.bottom || "0";
      const left = m.left || "0";
      wrapperStyle.margin = `${top}${unit} ${right}${unit} ${bottom}${unit} ${left}${unit}`;
    } else {
      // tabs-style numeric shape
      const top = m.top || 0;
      const right = m.right || 0;
      const bottom = m.bottom || 0;
      const left = m.left || 0;
      wrapperStyle.margin = `${top}px ${right}px ${bottom}px ${left}px`;
    }
  }
  if (attributes._padding) {
    const p = attributes._padding;
    if (p.unit) {
      const unit = p.unit || "px";
      const top = p.top || "0";
      const right = p.right || "0";
      const bottom = p.bottom || "0";
      const left = p.left || "0";
      wrapperStyle.padding = `${top}${unit} ${right}${unit} ${bottom}${unit} ${left}${unit}`;
    } else {
      const top = p.top || 0;
      const right = p.right || 0;
      const bottom = p.bottom || 0;
      const left = p.left || 0;
      wrapperStyle.padding = `${top}px ${right}px ${bottom}px ${left}px`;
    }
  }

  return (
    <div {...blockProps} style={wrapperStyle}>
      {src ? (
        <iframe
          src={src}
          style={iframeStyle}
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
        ></iframe>
      ) : (
        <div
          style={{
            height: `${height}px`,
            background: "#f3f3f3",
            border: "1px solid #ddd",
          }}
        />
      )}
    </div>
  );
};

export default Save;
