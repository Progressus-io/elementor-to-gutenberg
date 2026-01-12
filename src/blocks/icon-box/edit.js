import { __ } from "@wordpress/i18n";
import {
  useBlockProps,
  InspectorControls,
  RichText,
  MediaUpload,
  MediaUploadCheck,
  BlockControls,
  AlignmentToolbar,
} from "@wordpress/block-editor";
import {
  PanelBody,
  Button,
  TextControl,
  SelectControl,
  RangeControl,
  Popover,
  SearchControl,
  TabPanel,
  ColorPicker,
  ToggleControl,
} from "@wordpress/components";
import { useState } from "@wordpress/element";

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */

// FontAwesome icons organized by categories
const FONTAWESOME_ICONS = {
  solid: [
    // Popular/Common icons
    "fa-star",
    "fa-heart",
    "fa-home",
    "fa-user",
    "fa-envelope",
    "fa-phone",
    "fa-search",
    "fa-shopping-cart",
    "fa-cog",
    "fa-download",
    "fa-upload",
    "fa-edit",
    "fa-trash",
    "fa-check",
    "fa-times",
    "fa-plus",
    "fa-minus",
    "fa-arrow-up",
    "fa-arrow-down",
    "fa-arrow-left",
    "fa-arrow-right",
    "fa-play",
    "fa-pause",
    "fa-stop",
    "fa-volume-up",
    "fa-volume-down",
    "fa-volume-mute",
    "fa-calendar",
    "fa-clock",
    // Business & Office
    "fa-briefcase",
    "fa-building",
    "fa-chart-bar",
    "fa-chart-line",
    "fa-chart-pie",
    "fa-clipboard",
    "fa-file",
    "fa-folder",
    "fa-print",
    "fa-save",
    "fa-calculator",
    "fa-handshake",
    "fa-users",
    "fa-user-tie",
    "fa-id-card",
    "fa-balance-scale",
    "fa-gavel",
    "fa-award",
    "fa-medal",
    "fa-trophy",
    // Technology
    "fa-laptop",
    "fa-desktop",
    "fa-mobile-alt",
    "fa-tablet-alt",
    "fa-keyboard",
    "fa-mouse",
    "fa-wifi",
    "fa-bluetooth",
    "fa-usb",
    "fa-plug",
    "fa-battery-full",
    "fa-camera",
    "fa-video",
    "fa-microphone",
    "fa-headphones",
    "fa-tv",
    "fa-gamepad",
    "fa-code",
    "fa-bug",
    "fa-database",
    "fa-server",
    // Travel & Transportation
    "fa-plane",
    "fa-car",
    "fa-bus",
    "fa-train",
    "fa-ship",
    "fa-bicycle",
    "fa-motorcycle",
    "fa-taxi",
    "fa-gas-pump",
    "fa-map",
    "fa-map-marker-alt",
    "fa-compass",
    "fa-suitcase",
    "fa-umbrella",
    "fa-hotel",
    "fa-bed",
    "fa-key",
    "fa-door-open",
    "fa-passport",
    "fa-ticket-alt",
    // Food & Dining
    "fa-utensils",
    "fa-coffee",
    "fa-wine-glass",
    "fa-beer",
    "fa-pizza-slice",
    "fa-hamburger",
    "fa-ice-cream",
    "fa-apple-alt",
    "fa-carrot",
    "fa-fish",
    "fa-egg",
    "fa-cheese",
    "fa-birthday-cake",
    // Health & Medical
    "fa-heart-pulse",
    "fa-stethoscope",
    "fa-pills",
    "fa-syringe",
    "fa-band-aid",
    "fa-hospital",
    "fa-ambulance",
    "fa-wheelchair",
    "fa-dna",
    "fa-microscope",
    "fa-x-ray",
    "fa-tooth",
    // Sports & Recreation
    "fa-football-ball",
    "fa-basketball-ball",
    "fa-baseball-ball",
    "fa-tennis-ball",
    "fa-volleyball-ball",
    "fa-golf-ball",
    "fa-bowling-ball",
    "fa-table-tennis",
    "fa-running",
    "fa-swimming-pool",
    "fa-dumbbell",
    "fa-hiking",
    "fa-campground",
    "fa-fire",
    "fa-mountain",
    // Weather
    "fa-sun",
    "fa-moon",
    "fa-cloud",
    "fa-cloud-rain",
    "fa-cloud-snow",
    "fa-bolt",
    "fa-rainbow",
    "fa-temperature-high",
    "fa-temperature-low",
    "fa-wind",
    "fa-tornado",
    "fa-hurricane",
    // Shopping & E-commerce
    "fa-shopping-bag",
    "fa-credit-card",
    "fa-money-bill",
    "fa-coins",
    "fa-receipt",
    "fa-tag",
    "fa-tags",
    "fa-gift",
    "fa-store",
    "fa-cash-register",
    "fa-barcode",
    "fa-percent",
  ],
  regular: [
    "fa-star",
    "fa-heart",
    "fa-envelope",
    "fa-file",
    "fa-folder",
    "fa-user",
    "fa-circle",
    "fa-square",
    "fa-calendar",
    "fa-clock",
    "fa-bookmark",
    "fa-comment",
    "fa-thumbs-up",
    "fa-thumbs-down",
    "fa-eye",
    "fa-eye-slash",
    "fa-bell",
    "fa-lightbulb",
    "fa-gem",
    "fa-paper-plane",
    "fa-flag",
    "fa-clipboard",
    "fa-edit",
    "fa-trash-alt",
    "fa-copy",
    "fa-save",
    "fa-image",
    "fa-images",
    "fa-play-circle",
    "fa-pause-circle",
    "fa-stop-circle",
  ],
  brands: [
    // Social Media
    "fa-facebook",
    "fa-twitter",
    "fa-instagram",
    "fa-linkedin",
    "fa-youtube",
    "fa-tiktok",
    "fa-snapchat",
    "fa-pinterest",
    "fa-reddit",
    "fa-discord",
    "fa-telegram",
    "fa-whatsapp",
    "fa-skype",
    "fa-zoom",
    "fa-slack",
    "fa-twitch",
    "fa-spotify",
    "fa-soundcloud",
    // Technology Companies
    "fa-apple",
    "fa-google",
    "fa-microsoft",
    "fa-amazon",
    "fa-meta",
    "fa-adobe",
    "fa-figma",
    "fa-sketch",
    "fa-dropbox",
    "fa-github",
    "fa-gitlab",
    "fa-bitbucket",
    "fa-npm",
    "fa-node-js",
    "fa-react",
    "fa-vuejs",
    "fa-angular",
    "fa-bootstrap",
    "fa-sass",
    // Development & Design
    "fa-html5",
    "fa-css3-alt",
    "fa-js",
    "fa-php",
    "fa-python",
    "fa-java",
    "fa-wordpress",
    "fa-drupal",
    "fa-joomla",
    "fa-shopify",
    "fa-magento",
    "fa-wix",
    "fa-squarespace",
    // Payment & Finance
    "fa-paypal",
    "fa-stripe",
    "fa-bitcoin",
    "fa-ethereum",
    "fa-cc-visa",
    "fa-cc-mastercard",
    "fa-cc-amex",
    "fa-cc-paypal",
    "fa-cc-apple-pay",
    "fa-cc-stripe",
    // Other Brands
    "fa-airbnb",
    "fa-uber",
    "fa-lyft",
    "fa-netflix",
    "fa-steam",
    "fa-playstation",
    "fa-xbox",
    "fa-nintendo-switch",
    "fa-android",
    "fa-linux",
    "fa-windows",
    "fa-docker",
    "fa-aws",
    "fa-cloudflare",
    "fa-jenkins",
    "fa-mailchimp",
  ],
};

export default function Edit({ attributes, setAttributes }) {
  const {
    icon = "fa-star",
    iconStyle = "fas",
    svg = "",
    svgUrl = "",
    size = 48,
    padding = 0,
    borderRadius = 0,
    color = "",
    backgroundColor = "",
    alignment = "left",
    iconAlign = "center",
    titleAlign = "left",
    descriptionAlign = "left",
    title = "",
    description = "",
    ariaLabel = "",
    titleSize = 0,
    titleColor = "",
    descriptionSize = 0,
    descriptionColor = "",
  } = attributes;

  const [isUploading, setIsUploading] = useState(false);

  const [isIconPickerOpen, setIsIconPickerOpen] = useState(false);
  const [iconSearch, setIconSearch] = useState("");
  const [showColorPicker, setShowColorPicker] = useState(false);
  const [showBgColorPicker, setShowBgColorPicker] = useState(false);
  const [showTitleColor, setShowTitleColor] = useState(false);
  const [showDescColor, setShowDescColor] = useState(false);

  // Filter icons based on search
  const getFilteredIcons = (category) => {
    return FONTAWESOME_ICONS[category].filter((iconName) =>
      iconName.toLowerCase().includes(iconSearch.toLowerCase())
    );
  };

    const iconStyles = {
    fontSize: `${size}px`,
    color: color,
    backgroundColor: backgroundColor || "transparent",
    borderRadius: borderRadius ? `${borderRadius}px` : "0",
    padding: padding ? `${padding}px` : "0",
    display: "inline-block",
    lineHeight: 1,
    transition: "all 0.3s ease",
    width: `${size}px`,
    height: `${size}px`,
  };

  // Create FontAwesome icon element
  const IconElement = () => {
    if (svg) {
      const svgWrapperStyles = {
        width: `${size}px`,
        height: `${size}px`,
        display: "inline-block",
        backgroundColor: backgroundColor || "transparent",
        borderRadius: borderRadius ? `${borderRadius}px` : "0",
        padding: padding ? `${padding}px` : "0",
        transition: "all 0.3s ease",
      };

      return (
        <span
          className={`gutenberg-icon-svg`}
          style={svgWrapperStyles}
          aria-label={ariaLabel}
          aria-hidden={!ariaLabel ? "true" : "false"}
          dangerouslySetInnerHTML={{ __html: svg }}
        />
      );
    }

    if (svgUrl) {
      const imgStyles = {
        width: `${size}px`,
        height: "auto",
        backgroundColor: backgroundColor || "transparent",
        borderRadius: borderRadius ? `${borderRadius}px` : "0",
        padding: padding ? `${padding}px` : "0",
        transition: "all 0.3s ease",
      };

      return <img src={svgUrl} style={imgStyles} alt={ariaLabel || ""} />;
    }

    return (
      <i
        className={`${iconStyle} ${icon}`}
        style={iconStyles}
        aria-label={ariaLabel}
        aria-hidden={!ariaLabel ? "true" : "false"}
      />
    );
  };
  
  const alignToJustify = (align) => {
    if (align === "left") return "flex-start";
    if (align === "right") return "flex-end";
    return "center";
  };

  const blockProps = useBlockProps({
    className: `fontawesome-icon-align-${alignment}`,
    style: {
      textAlign: alignment,
    },
  });

  const onSelectMedia = (media) => {
    const url = media && media.url ? media.url : "";
    if (url && url.toLowerCase().endsWith(".svg")) {
      setIsUploading(true);
      fetch(url)
        .then((res) => res.text())
        .then((text) => setAttributes({ svg: text, svgUrl: url }))
        .catch(() => setAttributes({ svg: "", svgUrl: url }))
        .finally(() => setIsUploading(false));
    } else {
      setAttributes({ svg: "", svgUrl: url });
    }
  };

  return (
    <>
      <BlockControls>
        <AlignmentToolbar
          value={alignment}
          onChange={(value) => setAttributes({ alignment: value || "left" })}
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody
          title={__("Icon Settings", "fontawesome-icon-block")}
          initialOpen={true}
        >
          <div className="fontawesome-icon-picker">
            <div className="fontawesome-icon-picker-preview">
              <div className="fontawesome-icon-current">
                <span className="current-icon-label">
                  {__("Current Icon:", "fontawesome-icon-block")}
                </span>
                <div className="current-icon-display">
                  <i
                    className={`${iconStyle} ${icon}`}
                    style={{ fontSize: "32px", color: color }}
                  />
                  <div className="icon-details">
                    <span className="icon-name">{icon}</span>
                    <span className="icon-style">{iconStyle}</span>
                  </div>
                </div>
              </div>
            </div>
            <div style={{ marginTop: 8 }}>
              <SelectControl
                label={__("Icon Alignment", "gutenberg")}
                value={iconAlign}
                onChange={(v) => setAttributes({ iconAlign: v })}
                options={[
                  { label: "Left", value: "left" },
                  { label: "Center", value: "center" },
                  { label: "Right", value: "right" },
                ]}
              />
            </div>
            <Button
              variant="secondary"
              onClick={() => setIsIconPickerOpen(!isIconPickerOpen)}
              className="fontawesome-icon-picker-button"
            >
              {__("Choose Different Icon", "fontawesome-icon-block")}
            </Button>

            {isIconPickerOpen && (
              <Popover
                position="middle center"
                onClose={() => setIsIconPickerOpen(false)}
                className="fontawesome-icon-picker-popover"
              >
                <div className="fontawesome-icon-picker-content">
                  <SearchControl
                    value={iconSearch}
                    onChange={setIconSearch}
                    placeholder={__(
                      "Search icons...",
                      "fontawesome-icon-block"
                    )}
                  />
                  <TabPanel
                    className="fontawesome-icon-tabs"
                    activeClass="active-tab"
                    tabs={[
                      {
                        name: "solid",
                        title: __("Solid", "fontawesome-icon-block"),
                        className: "tab-solid",
                      },
                      {
                        name: "regular",
                        title: __("Regular", "fontawesome-icon-block"),
                        className: "tab-regular",
                      },
                      {
                        name: "brands",
                        title: __("Brands", "fontawesome-icon-block"),
                        className: "tab-brands",
                      },
                    ]}
                  >
                    {(tab) => {
                      const stylePrefix =
                        tab.name === "solid"
                          ? "fas"
                          : tab.name === "regular"
                          ? "far"
                          : "fab";
                      const filteredIcons = getFilteredIcons(tab.name);

                      return (
                        <div className="fontawesome-icon-grid">
                          {filteredIcons.map((iconName) => (
                            <Button
                              key={`${stylePrefix}-${iconName}`}
                              onClick={() => {
                                setAttributes({
                                  icon: iconName,
                                  iconStyle: stylePrefix,
                                });
                                setIsIconPickerOpen(false);
                              }}
                              className={`fontawesome-icon-option ${
                                iconName === icon && stylePrefix === iconStyle
                                  ? "is-selected"
                                  : ""
                              }`}
                              title={`${iconName} (${stylePrefix})`}
                            >
                              <i className={`${stylePrefix} ${iconName}`} />
                            </Button>
                          ))}
                        </div>
                      );
                    }}
                  </TabPanel>
                </div>
              </Popover>
            )}
          </div>

          <div className="fontawesome-svg-upload" style={{ marginTop: "12px" }}>
            <div className="components-base-control__label">
              {__("SVG Upload", "fontawesome-icon-block")}
            </div>
            {svg ? (
              <div
                className="svg-preview"
                style={{ marginBottom: "8px" }}
                dangerouslySetInnerHTML={{ __html: svg }}
              />
            ) : svgUrl ? (
              <img
                src={svgUrl}
                alt={ariaLabel || ""}
                style={{ width: "64px", height: "auto", marginBottom: "8px" }}
              />
            ) : null}
            <MediaUploadCheck>
              <MediaUpload
                onSelect={(media) => {
                  const url = media && media.url ? media.url : "";
                  if (url && url.toLowerCase().endsWith(".svg")) {
                    fetch(url)
                      .then((res) => res.text())
                      .then((text) =>
                        setAttributes({ svg: text, svgUrl: url })
                      );
                  } else {
                    setAttributes({ svg: "", svgUrl: url });
                  }
                }}
                allowedTypes={["image"]}
                render={({ open }) => (
                  <Button isSecondary onClick={open}>
                    {svg || svgUrl
                      ? __("Replace SVG", "fontawesome-icon-block")
                      : __("Upload SVG", "fontawesome-icon-block")}
                  </Button>
                )}
              />
            </MediaUploadCheck>
            {(svg || svgUrl) && (
              <Button
                isLink
                onClick={() => setAttributes({ svg: "", svgUrl: "" })}
              >
                {__("Remove SVG", "fontawesome-icon-block")}
              </Button>
            )}

            <RangeControl
              label={__("Size", "fontawesome-icon-block")}
              value={size}
              onChange={(value) => setAttributes({ size: value })}
              min={16}
              max={200}
              step={2}
            />

            <RangeControl
              label={__("Padding", "fontawesome-icon-block")}
              value={padding}
              onChange={(value) => setAttributes({ padding: value })}
              min={0}
              max={50}
              step={1}
            />

            <RangeControl
              label={__("Border Radius", "fontawesome-icon-block")}
              value={borderRadius}
              onChange={(value) => setAttributes({ borderRadius: value })}
              min={0}
              max={50}
              step={1}
            />

            <div style={{ marginTop: 8 }}>
              <SelectControl
                label={__("Title Alignment", "gutenberg")}
                value={titleAlign}
                onChange={(v) => setAttributes({ titleAlign: v })}
                options={[
                  { label: "Left", value: "left" },
                  { label: "Center", value: "center" },
                  { label: "Right", value: "right" },
                ]}
              />

              <SelectControl
                label={__("Description Alignment", "gutenberg")}
                value={descriptionAlign}
                onChange={(v) => setAttributes({ descriptionAlign: v })}
                options={[
                  { label: "Left", value: "left" },
                  { label: "Center", value: "center" },
                  { label: "Right", value: "right" },
                ]}
              />
            </div>
          </div>

          <PanelBody
            title={__("Colors", "fontawesome-icon-block")}
            initialOpen={false}
          >
            <div className="components-base-control">
              <div className="components-base-control__label">
                {__("Icon Color", "fontawesome-icon-block")}
              </div>
              <Button
                className="fontawesome-icon-color-button"
                onClick={() => setShowColorPicker(!showColorPicker)}
                style={{ backgroundColor: color }}
              >
                {color || __("Choose Color", "fontawesome-icon-block")}
              </Button>
              {showColorPicker && (
                <Popover
                  position="bottom left"
                  onClose={() => setShowColorPicker(false)}
                >
                  <ColorPicker
                    color={color}
                    onChange={(value) => setAttributes({ color: value })}
                    enableAlpha
                  />
                </Popover>
              )}
            </div>

            <div className="components-base-control">
              <div className="components-base-control__label">
                {__("Background Color", "fontawesome-icon-block")}
              </div>
              <Button
                className="fontawesome-icon-color-button"
                onClick={() => setShowBgColorPicker(!showBgColorPicker)}
                style={{ backgroundColor: backgroundColor || "#transparent" }}
              >
                {backgroundColor ||
                  __("Choose Color", "fontawesome-icon-block")}
              </Button>
              {showBgColorPicker && (
                <Popover
                  position="bottom left"
                  onClose={() => setShowBgColorPicker(false)}
                >
                  <ColorPicker
                    color={backgroundColor}
                    onChange={(value) =>
                      setAttributes({ backgroundColor: value })
                    }
                    enableAlpha
                  />
                </Popover>
              )}
            </div>
          </PanelBody>

          <PanelBody
            title={__("Title Styles", "gutenberg")}
            initialOpen={false}
          >
            <RangeControl
              label={__("Title Size", "gutenberg")}
              value={titleSize}
              onChange={(v) => setAttributes({ titleSize: v })}
              min={8}
              max={72}
            />
            <div>
              <Button
                className="fontawesome-icon-color-button"
                onClick={() => setShowTitleColor(!showTitleColor)}
                style={{ backgroundColor: titleColor }}
              >
                {titleColor || __("Title Color", "gutenberg")}
              </Button>
              {showTitleColor && (
                <Popover
                  position="bottom left"
                  onClose={() => setShowTitleColor(false)}
                >
                  <ColorPicker
                    color={titleColor}
                    onChange={(value) => setAttributes({ titleColor: value })}
                    enableAlpha
                  />
                </Popover>
              )}
            </div>
          </PanelBody>

          <PanelBody
            title={__("Description Styles", "gutenberg")}
            initialOpen={false}
          >
            <RangeControl
              label={__("Description Size", "gutenberg")}
              value={descriptionSize}
              onChange={(v) => setAttributes({ descriptionSize: v })}
              min={8}
              max={48}
            />
            <div>
              <Button
                className="fontawesome-icon-color-button"
                onClick={() => setShowDescColor(!showDescColor)}
                style={{ backgroundColor: descriptionColor }}
              >
                {descriptionColor || __("Description Color", "gutenberg")}
              </Button>
              {showDescColor && (
                <Popover
                  position="bottom left"
                  onClose={() => setShowDescColor(false)}
                >
                  <ColorPicker
                    color={descriptionColor}
                    onChange={(value) =>
                      setAttributes({ descriptionColor: value })
                    }
                    enableAlpha
                  />
                </Popover>
              )}
            </div>
          </PanelBody>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps} style={{ textAlign: alignment }}>
        <div
          className="icon-box__inner"
          style={{ display: "flex", alignItems: "flex-start", gap: 12 }}
        >
          <div
            className="icon-box__icon"
            style={{
              width: size,
              height: size,
              display: "flex",
              alignItems: "center",
              justifyContent: alignToJustify(iconAlign),
            }}
          >
            <IconElement />
          </div>

          <div className="icon-box__content">
            <RichText
              tagName="h3"
              value={title}
              onChange={(v) => setAttributes({ title: v })}
              style={{ textAlign: titleAlign }}
              placeholder={__("Title", "gutenberg")}
            />
            <RichText
              tagName="p"
              value={description}
              onChange={(v) => setAttributes({ description: v })}
              style={{ textAlign: descriptionAlign }}
              placeholder={__("Description", "gutenberg")}
            />
          </div>
        </div>
      </div>
    </>
  );
}
