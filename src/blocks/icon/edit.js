import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
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
} from '@wordpress/components';

import { useState } from '@wordpress/element';
import './editor.scss';

const DASHICONS = [
	'menu', 'dashboard', 'admin-site', 'star-filled', 'star-half', 'star-empty',
	'heart', 'facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'cart', 'cloud'
];

export default function Edit({ attributes, setAttributes }) {
	const {
		icon,
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
		ariaLabel,
	} = attributes;

	const [isIconPickerOpen, setIsIconPickerOpen] = useState(false);
	const [iconSearch, setIconSearch] = useState('');

	const filteredIcons = DASHICONS.filter((iconName) =>
		iconName.toLowerCase().includes(iconSearch.toLowerCase())
	);

	const blockProps = useBlockProps({
		className: `styled-icon-align-${alignment}`,
		style: { textAlign: alignment },
	});

	const iconStyles = {
		fontSize: `${size}px`,
		color: color,
		backgroundColor: backgroundColor || 'transparent',
		borderRadius: borderRadius ? `${borderRadius}px` : '0',
		padding: padding ? `${padding}px` : '0',
		display: 'inline-block',
		lineHeight: 1,
		transition: 'all 0.3s ease',
	};

	const iconClasses = `dashicons dashicons-${icon} styled-icon-hover-${hoverEffect}`;

	return (
		<>
			<BlockControls>
				<AlignmentToolbar
					value={alignment}
					onChange={(value) => setAttributes({ alignment: value || 'left' })}
				/>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Icon Settings', 'styled-icon-block')} initialOpen={true}>
					<Button
						variant="secondary"
						onClick={() => setIsIconPickerOpen(!isIconPickerOpen)}
					>
						<span className={iconClasses} style={{ fontSize: '20px', marginRight: '8px' }}></span>
						{__('Choose Icon', 'styled-icon-block')}
					</Button>

					{isIconPickerOpen && (
						<Popover position="middle center" onClose={() => setIsIconPickerOpen(false)}>
							<div>
								<SearchControl
									value={iconSearch}
									onChange={setIconSearch}
									placeholder={__('Search icons...', 'gutenberg')}
								/>
								<div className="styled-icon-grid">
									{filteredIcons.map((iconName) => (
										<Button
											key={iconName}
											onClick={() => {
												setAttributes({ icon: iconName });
												setIsIconPickerOpen(false);
											}}
											className={iconName === icon ? 'is-selected' : ''}
											title={iconName}
										>
											<span className={`dashicons dashicons-${iconName}`}></span>
										</Button>
									))}
								</div>
							</div>
						</Popover>
					)}

					<RangeControl
						label={__('Size', 'gutenberg')}
						value={size}
						onChange={(value) => setAttributes({ size: value })}
						min={16}
						max={200}
						step={2}
					/>
					<RangeControl
						label={__('Padding', 'gutenberg')}
						value={padding}
						onChange={(value) => setAttributes({ padding: value })}
						min={0}
						max={50}
						step={1}
					/>
					<RangeControl
						label={__('Border Radius', 'gutenberg')}
						value={borderRadius}
						onChange={(value) => setAttributes({ borderRadius: value })}
						min={0}
						max={50}
						step={1}
					/>
				</PanelBody>

				<PanelBody title={__('Colors', 'gutenberg')} initialOpen={false}>
					<div>
						<p>{__('Icon Color', 'gutenberg')}</p>
						<ColorPicker
							color={color}
							onChange={(value) => setAttributes({ color: value })}
							enableAlpha
						/>
					</div>

					<div>
						<p>{__('Background Color', 'gutenberg')}</p>
						<ColorPicker
							color={backgroundColor}
							onChange={(value) => setAttributes({ backgroundColor: value })}
							enableAlpha
						/>
					</div>
				</PanelBody>

				<PanelBody title={__('Hover Effects', 'gutenberg')} initialOpen={false}>
					<SelectControl
						label={__('Hover Effect', 'gutenberg')}
						value={hoverEffect}
						onChange={(value) => setAttributes({ hoverEffect: value })}
						options={[
							{ label: __('None', 'gutenberg'), value: 'none' },
							{ label: __('Scale Up', 'gutenberg'), value: 'scale-up' },
							{ label: __('Rotate', 'gutenberg'), value: 'rotate' },
							{ label: __('Pulse', 'gutenberg'), value: 'pulse' },
						]}
					/>
				</PanelBody>

				<PanelBody title={__('Link Settings', 'gutenberg')} initialOpen={false}>
					<TextControl
						label={__('Link URL', 'gutenberg')}
						value={link}
						onChange={(value) => setAttributes({ link: value })}
					/>
					{link && (
						<ToggleControl
							label={__('Open in new tab', 'gutenberg')}
							checked={linkTarget}
							onChange={(value) => setAttributes({ linkTarget: value })}
						/>
					)}
					<TextControl
						label={__('Accessible Label (aria-label)', 'gutenberg')}
						value={ariaLabel}
						onChange={(value) => setAttributes({ ariaLabel: value })}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<span className={iconClasses} style={iconStyles} aria-label={ariaLabel}></span>
			</div>
		</>
	);
}
