/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { 
	useBlockProps, 
	InspectorControls,
	BlockControls
} from '@wordpress/block-editor';

import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	Button,
	ColorPicker,
	Popover,
	SearchControl,
	TabPanel
} from '@wordpress/components';

import { 
	useState
} from '@wordpress/element';

import {
	AlignmentToolbar
} from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

// FontAwesome icons organized by categories
const FONTAWESOME_ICONS = {
	solid: [
		// Popular/Common icons
		'fa-star', 'fa-heart', 'fa-home', 'fa-user', 'fa-envelope', 'fa-phone', 'fa-search', 'fa-shopping-cart',
		'fa-cog', 'fa-download', 'fa-upload', 'fa-edit', 'fa-trash', 'fa-check', 'fa-times', 'fa-plus',
		'fa-minus', 'fa-arrow-up', 'fa-arrow-down', 'fa-arrow-left', 'fa-arrow-right', 'fa-play', 'fa-pause',
		'fa-stop', 'fa-volume-up', 'fa-volume-down', 'fa-volume-mute', 'fa-calendar', 'fa-clock',
		// Business & Office
		'fa-briefcase', 'fa-building', 'fa-chart-bar', 'fa-chart-line', 'fa-chart-pie', 'fa-clipboard',
		'fa-file', 'fa-folder', 'fa-print', 'fa-save', 'fa-calculator', 'fa-handshake', 'fa-users',
		'fa-user-tie', 'fa-id-card', 'fa-balance-scale', 'fa-gavel', 'fa-award', 'fa-medal', 'fa-trophy',
		// Technology
		'fa-laptop', 'fa-desktop', 'fa-mobile-alt', 'fa-tablet-alt', 'fa-keyboard', 'fa-mouse', 'fa-wifi',
		'fa-bluetooth', 'fa-usb', 'fa-plug', 'fa-battery-full', 'fa-camera', 'fa-video', 'fa-microphone',
		'fa-headphones', 'fa-tv', 'fa-gamepad', 'fa-code', 'fa-bug', 'fa-database', 'fa-server',
		// Travel & Transportation
		'fa-plane', 'fa-car', 'fa-bus', 'fa-train', 'fa-ship', 'fa-bicycle', 'fa-motorcycle', 'fa-taxi',
		'fa-gas-pump', 'fa-map', 'fa-map-marker-alt', 'fa-compass', 'fa-suitcase', 'fa-umbrella',
		'fa-hotel', 'fa-bed', 'fa-key', 'fa-door-open', 'fa-passport', 'fa-ticket-alt',
		// Food & Dining
		'fa-utensils', 'fa-coffee', 'fa-wine-glass', 'fa-beer', 'fa-pizza-slice', 'fa-hamburger',
		'fa-ice-cream', 'fa-apple-alt', 'fa-carrot', 'fa-fish', 'fa-egg', 'fa-cheese', 'fa-birthday-cake',
		// Health & Medical
		'fa-heart-pulse', 'fa-stethoscope', 'fa-pills', 'fa-syringe', 'fa-band-aid', 'fa-hospital',
		'fa-ambulance', 'fa-wheelchair', 'fa-dna', 'fa-microscope', 'fa-x-ray', 'fa-tooth',
		// Sports & Recreation
		'fa-football-ball', 'fa-basketball-ball', 'fa-baseball-ball', 'fa-tennis-ball', 'fa-volleyball-ball',
		'fa-golf-ball', 'fa-bowling-ball', 'fa-table-tennis', 'fa-running', 'fa-swimming-pool',
		'fa-dumbbell', 'fa-hiking', 'fa-campground', 'fa-fire', 'fa-mountain',
		// Weather
		'fa-sun', 'fa-moon', 'fa-cloud', 'fa-cloud-rain', 'fa-cloud-snow', 'fa-bolt', 'fa-rainbow',
		'fa-temperature-high', 'fa-temperature-low', 'fa-wind', 'fa-tornado', 'fa-hurricane',
		// Shopping & E-commerce
		'fa-shopping-bag', 'fa-credit-card', 'fa-money-bill', 'fa-coins', 'fa-receipt', 'fa-tag',
		'fa-tags', 'fa-gift', 'fa-store', 'fa-cash-register', 'fa-barcode', 'fa-percent'
	],
	regular: [
		'fa-star', 'fa-heart', 'fa-envelope', 'fa-file', 'fa-folder', 'fa-user', 'fa-circle',
		'fa-square', 'fa-calendar', 'fa-clock', 'fa-bookmark', 'fa-comment', 'fa-thumbs-up',
		'fa-thumbs-down', 'fa-eye', 'fa-eye-slash', 'fa-bell', 'fa-lightbulb', 'fa-gem',
		'fa-paper-plane', 'fa-flag', 'fa-clipboard', 'fa-edit', 'fa-trash-alt', 'fa-copy',
		'fa-save', 'fa-image', 'fa-images', 'fa-play-circle', 'fa-pause-circle', 'fa-stop-circle'
	],
	brands: [
		// Social Media
		'fa-facebook', 'fa-twitter', 'fa-instagram', 'fa-linkedin', 'fa-youtube', 'fa-tiktok',
		'fa-snapchat', 'fa-pinterest', 'fa-reddit', 'fa-discord', 'fa-telegram', 'fa-whatsapp',
		'fa-skype', 'fa-zoom', 'fa-slack', 'fa-twitch', 'fa-spotify', 'fa-soundcloud',
		// Technology Companies
		'fa-apple', 'fa-google', 'fa-microsoft', 'fa-amazon', 'fa-meta', 'fa-adobe', 'fa-figma',
		'fa-sketch', 'fa-dropbox', 'fa-github', 'fa-gitlab', 'fa-bitbucket', 'fa-npm',
		'fa-node-js', 'fa-react', 'fa-vuejs', 'fa-angular', 'fa-bootstrap', 'fa-sass',
		// Development & Design
		'fa-html5', 'fa-css3-alt', 'fa-js', 'fa-php', 'fa-python', 'fa-java', 'fa-wordpress',
		'fa-drupal', 'fa-joomla', 'fa-shopify', 'fa-magento', 'fa-wix', 'fa-squarespace',
		// Payment & Finance
		'fa-paypal', 'fa-stripe', 'fa-bitcoin', 'fa-ethereum', 'fa-cc-visa', 'fa-cc-mastercard',
		'fa-cc-amex', 'fa-cc-paypal', 'fa-cc-apple-pay', 'fa-cc-stripe',
		// Other Brands
		'fa-airbnb', 'fa-uber', 'fa-lyft', 'fa-netflix', 'fa-steam', 'fa-playstation',
		'fa-xbox', 'fa-nintendo-switch', 'fa-android', 'fa-linux', 'fa-windows',
		'fa-docker', 'fa-aws', 'fa-cloudflare', 'fa-jenkins', 'fa-mailchimp'
	]
};

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		icon,
		iconStyle,
		size,
		color,
		backgroundColor,
		borderRadius,
		padding,
		alignment,
		hoverColor,
		hoverBackgroundColor,
		hoverEffect,
		link,
		linkTarget,
		ariaLabel
	} = attributes;

	const [ isIconPickerOpen, setIsIconPickerOpen ] = useState( false );
	const [ iconSearch, setIconSearch ] = useState( '' );
	const [ showColorPicker, setShowColorPicker ] = useState( false );
	const [ showBgColorPicker, setShowBgColorPicker ] = useState( false );
	const [ showHoverColorPicker, setShowHoverColorPicker ] = useState( false );
	const [ showHoverBgColorPicker, setShowHoverBgColorPicker ] = useState( false );

	// Filter icons based on search
	const getFilteredIcons = ( category ) => {
		return FONTAWESOME_ICONS[category].filter( iconName =>
			iconName.toLowerCase().includes( iconSearch.toLowerCase() )
		);
	};

	const blockProps = useBlockProps( {
		className: `fontawesome-icon-align-${alignment}`,
		style: {
			textAlign: alignment
		}
	} );

	const iconStyles = {
		fontSize: `${size}px`,
		color: color,
		backgroundColor: backgroundColor || 'transparent',
		borderRadius: borderRadius ? `${borderRadius}px` : '0',
		padding: padding ? `${padding}px` : '0',
		display: 'inline-block',
		lineHeight: 1,
		transition: 'all 0.3s ease',
		width: 'auto',
		height: 'auto'
	};

	// Create FontAwesome icon element
	const IconElement = () => {
		return (
			<i 
				className={ `${iconStyle} ${icon} fontawesome-icon-hover-${hoverEffect}` }
				style={ iconStyles }
				aria-label={ ariaLabel }
				aria-hidden={ !ariaLabel ? 'true' : 'false' }
			/>
		);
	};

	return (
		<>
			<BlockControls>
				<AlignmentToolbar
					value={ alignment }
					onChange={ ( value ) => setAttributes( { alignment: value || 'left' } ) }
				/>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Icon Settings', 'fontawesome-icon-block' ) } initialOpen={ true }>
					<div className="fontawesome-icon-picker">
						<div className="fontawesome-icon-picker-preview">
							<div className="fontawesome-icon-current">
								<span className="current-icon-label">{ __( 'Current Icon:', 'fontawesome-icon-block' ) }</span>
								<div className="current-icon-display">
									<i 
										className={ `${iconStyle} ${icon}` }
										style={ { 
											fontSize: '32px',
											color: color
										} }
									/>
									<div className="icon-details">
										<span className="icon-name">{ icon }</span>
										<span className="icon-style">{ iconStyle }</span>
									</div>
								</div>
							</div>
						</div>
						<Button
							variant="secondary"
							onClick={ () => setIsIconPickerOpen( ! isIconPickerOpen ) }
							className="fontawesome-icon-picker-button"
						>
							{ __( 'Choose Different Icon', 'fontawesome-icon-block' ) }
						</Button>
						
						{ isIconPickerOpen && (
							<Popover
								position="middle center"
								onClose={ () => setIsIconPickerOpen( false ) }
								className="fontawesome-icon-picker-popover"
							>
								<div className="fontawesome-icon-picker-content">
									<SearchControl
										value={ iconSearch }
										onChange={ setIconSearch }
										placeholder={ __( 'Search icons...', 'fontawesome-icon-block' ) }
									/>
									<TabPanel
										className="fontawesome-icon-tabs"
										activeClass="active-tab"
										tabs={ [
											{ name: 'solid', title: __( 'Solid', 'fontawesome-icon-block' ), className: 'tab-solid' },
											{ name: 'regular', title: __( 'Regular', 'fontawesome-icon-block' ), className: 'tab-regular' },
											{ name: 'brands', title: __( 'Brands', 'fontawesome-icon-block' ), className: 'tab-brands' }
										] }
									>
										{ ( tab ) => {
											const stylePrefix = tab.name === 'solid' ? 'fas' : tab.name === 'regular' ? 'far' : 'fab';
											const filteredIcons = getFilteredIcons( tab.name );
											
											return (
												<div className="fontawesome-icon-grid">
													{ filteredIcons.map( ( iconName ) => (
														<Button
															key={ `${stylePrefix}-${iconName}` }
															onClick={ () => {
																setAttributes( { 
																	icon: iconName,
																	iconStyle: stylePrefix
																} );
																setIsIconPickerOpen( false );
															} }
															className={ `fontawesome-icon-option ${iconName === icon && stylePrefix === iconStyle ? 'is-selected' : ''}` }
															title={ `${iconName} (${stylePrefix})` }
														>
															<i className={ `${stylePrefix} ${iconName}` } />
														</Button>
													) ) }
												</div>
											);
										} }
									</TabPanel>
								</div>
							</Popover>
						) }
					</div>

					<RangeControl
						label={ __( 'Size', 'fontawesome-icon-block' ) }
						value={ size }
						onChange={ ( value ) => setAttributes( { size: value } ) }
						min={ 16 }
						max={ 200 }
						step={ 2 }
					/>

					<RangeControl
						label={ __( 'Padding', 'fontawesome-icon-block' ) }
						value={ padding }
						onChange={ ( value ) => setAttributes( { padding: value } ) }
						min={ 0 }
						max={ 50 }
						step={ 1 }
					/>

					<RangeControl
						label={ __( 'Border Radius', 'fontawesome-icon-block' ) }
						value={ borderRadius }
						onChange={ ( value ) => setAttributes( { borderRadius: value } ) }
						min={ 0 }
						max={ 50 }
						step={ 1 }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Colors', 'fontawesome-icon-block' ) } initialOpen={ false }>
					<div className="components-base-control">
						<div className="components-base-control__label">
							{ __( 'Icon Color', 'fontawesome-icon-block' ) }
						</div>
						<Button
							className="fontawesome-icon-color-button"
							onClick={ () => setShowColorPicker( ! showColorPicker ) }
							style={ { backgroundColor: color } }
						>
							{ color || __( 'Choose Color', 'fontawesome-icon-block' ) }
						</Button>
						{ showColorPicker && (
							<Popover
								position="bottom left"
								onClose={ () => setShowColorPicker( false ) }
							>
								<ColorPicker
									color={ color }
									onChange={ ( value ) => setAttributes( { color: value } ) }
									enableAlpha
								/>
							</Popover>
						) }
					</div>

					<div className="components-base-control">
						<div className="components-base-control__label">
							{ __( 'Background Color', 'fontawesome-icon-block' ) }
						</div>
						<Button
							className="fontawesome-icon-color-button"
							onClick={ () => setShowBgColorPicker( ! showBgColorPicker ) }
							style={ { backgroundColor: backgroundColor || '#transparent' } }
						>
							{ backgroundColor || __( 'Choose Color', 'fontawesome-icon-block' ) }
						</Button>
						{ showBgColorPicker && (
							<Popover
								position="bottom left"
								onClose={ () => setShowBgColorPicker( false ) }
							>
								<ColorPicker
									color={ backgroundColor }
									onChange={ ( value ) => setAttributes( { backgroundColor: value } ) }
									enableAlpha
								/>
							</Popover>
						) }
					</div>
				</PanelBody>

				<PanelBody title={ __( 'Hover Effects', 'fontawesome-icon-block' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Hover Effect', 'fontawesome-icon-block' ) }
						value={ hoverEffect }
						onChange={ ( value ) => setAttributes( { hoverEffect: value } ) }
						options={ [
							{ label: __( 'None', 'fontawesome-icon-block' ), value: 'none' },
							{ label: __( 'Scale Up', 'fontawesome-icon-block' ), value: 'scale-up' },
							{ label: __( 'Scale Down', 'fontawesome-icon-block' ), value: 'scale-down' },
							{ label: __( 'Rotate', 'fontawesome-icon-block' ), value: 'rotate' },
							{ label: __( 'Bounce', 'fontawesome-icon-block' ), value: 'bounce' },
							{ label: __( 'Pulse', 'fontawesome-icon-block' ), value: 'pulse' }
						] }
					/>

					<div className="components-base-control">
						<div className="components-base-control__label">
							{ __( 'Hover Color', 'fontawesome-icon-block' ) }
						</div>
						<Button
							className="fontawesome-icon-color-button"
							onClick={ () => setShowHoverColorPicker( ! showHoverColorPicker ) }
							style={ { backgroundColor: hoverColor || '#transparent' } }
						>
							{ hoverColor || __( 'Choose Color', 'fontawesome-icon-block' ) }
						</Button>
						{ showHoverColorPicker && (
							<Popover
								position="bottom left"
								onClose={ () => setShowHoverColorPicker( false ) }
							>
								<ColorPicker
									color={ hoverColor }
									onChange={ ( value ) => setAttributes( { hoverColor: value } ) }
									enableAlpha
								/>
							</Popover>
						) }
					</div>

					<div className="components-base-control">
						<div className="components-base-control__label">
							{ __( 'Hover Background Color', 'fontawesome-icon-block' ) }
						</div>
						<Button
							className="fontawesome-icon-color-button"
							onClick={ () => setShowHoverBgColorPicker( ! showHoverBgColorPicker ) }
							style={ { backgroundColor: hoverBackgroundColor || '#transparent' } }
						>
							{ hoverBackgroundColor || __( 'Choose Color', 'fontawesome-icon-block' ) }
						</Button>
						{ showHoverBgColorPicker && (
							<Popover
								position="bottom left"
								onClose={ () => setShowHoverBgColorPicker( false ) }
							>
								<ColorPicker
									color={ hoverBackgroundColor }
									onChange={ ( value ) => setAttributes( { hoverBackgroundColor: value } ) }
									enableAlpha
								/>
							</Popover>
						) }
					</div>
				</PanelBody>

				<PanelBody title={ __( 'Link Settings', 'fontawesome-icon-block' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Link URL', 'fontawesome-icon-block' ) }
						value={ link }
						onChange={ ( value ) => setAttributes( { link: value } ) }
						placeholder={ __( 'Enter URL...', 'fontawesome-icon-block' ) }
					/>

					{ link && (
						<ToggleControl
							label={ __( 'Open in new tab', 'fontawesome-icon-block' ) }
							checked={ linkTarget }
							onChange={ ( value ) => setAttributes( { linkTarget: value } ) }
						/>
					) }

					<TextControl
						label={ __( 'Accessible Label (aria-label)', 'fontawesome-icon-block' ) }
						value={ ariaLabel }
						onChange={ ( value ) => setAttributes( { ariaLabel: value } ) }
						placeholder={ __( 'Describe the icon...', 'fontawesome-icon-block' ) }
						help={ __( 'Helps screen readers understand the purpose of this icon', 'fontawesome-icon-block' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<IconElement />
			</div>
		</>
	);
}