import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	Placeholder,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './editor.scss';

/**
 * Public content types and taxonomies, printed by PHP (parich_sitemap_editor_data()).
 * The REST API would leave out types registered without show_in_rest.
 */
const {
	postTypes = [],
	taxonomies = [],
	postsPerCategory: postsPerCategorySetting = 0,
} = window.parichSitemapEditor || {};

const SECTION_OPTIONS = [
	{
		value: '',
		label: __( 'Everything (full sitemap)', 'parich-accessible-sitemap' ),
	},
	{ value: 'page', label: __( 'Pages', 'parich-accessible-sitemap' ) },
	{
		value: 'post',
		label: __( 'Posts by category', 'parich-accessible-sitemap' ),
	},
	{
		value: 'category',
		label: __( 'Categories', 'parich-accessible-sitemap' ),
	},
	{ value: 'tag', label: __( 'Tags', 'parich-accessible-sitemap' ) },
	{ value: 'archive', label: __( 'Archives', 'parich-accessible-sitemap' ) },
	{ value: 'author', label: __( 'Authors', 'parich-accessible-sitemap' ) },
];

/**
 * Sort values each list understands (see Parich_Sitemap_Renderer::SORT_VALUES).
 * The first option is the list's own default order.
 *
 * @param {string} only Value of the "Show" setting.
 * @return {Array|null} SelectControl options, or null when the list has no sort choice.
 */
function getSortOptions( only ) {
	if ( only === 'page' ) {
		return [
			{
				value: '',
				label: __(
					'Menu order, then title',
					'parich-accessible-sitemap'
				),
			},
			{
				value: 'post_title',
				label: __( 'Title', 'parich-accessible-sitemap' ),
			},
			{
				value: 'post_date',
				label: __( 'Date published', 'parich-accessible-sitemap' ),
			},
			{
				value: 'post_modified',
				label: __( 'Date modified', 'parich-accessible-sitemap' ),
			},
		];
	}
	if ( only === 'post' ) {
		return [
			{
				value: '',
				label: __(
					'Categories by name, newest posts first',
					'parich-accessible-sitemap'
				),
			},
			{
				value: 'count',
				label: __(
					'Categories by number of posts',
					'parich-accessible-sitemap'
				),
			},
			{
				value: 'post_name',
				label: __( 'Posts by name', 'parich-accessible-sitemap' ),
			},
			{
				value: 'post_date',
				label: __( 'Posts by date', 'parich-accessible-sitemap' ),
			},
		];
	}
	if ( only === 'category' ) {
		return [
			{ value: '', label: __( 'Name', 'parich-accessible-sitemap' ) },
			{
				value: 'count',
				label: __( 'Number of posts', 'parich-accessible-sitemap' ),
			},
			{ value: 'slug', label: __( 'Slug', 'parich-accessible-sitemap' ) },
		];
	}
	if ( only === 'author' ) {
		return [
			{ value: '', label: __( 'Name', 'parich-accessible-sitemap' ) },
			{
				value: 'post_count',
				label: __( 'Number of posts', 'parich-accessible-sitemap' ),
			},
			{
				value: 'registered',
				label: __( 'Date registered', 'parich-accessible-sitemap' ),
			},
		];
	}
	if ( postTypes.some( ( type ) => type.value === only ) ) {
		return [
			{
				value: '',
				label: __( 'Newest first', 'parich-accessible-sitemap' ),
			},
			{
				value: 'post_title',
				label: __( 'Title', 'parich-accessible-sitemap' ),
			},
			{
				value: 'menu_order',
				label: __( 'Menu order', 'parich-accessible-sitemap' ),
			},
		];
	}
	// Full sitemap, tags, archives and taxonomies keep their default order.
	return null;
}

/**
 * Built-in sections that can be renamed (sectionTitles → pages_title, …).
 *
 * @param {string} only Value of the "Show" setting.
 * @return {Array} Options of the sections shown, with their default titles.
 */
function getRenamableSections( only ) {
	const keys =
		only === '' ? [ 'page', 'post', 'archive', 'author' ] : [ only ];
	return SECTION_OPTIONS.filter(
		( option ) => option.value !== '' && keys.includes( option.value )
	);
}

const HEADING_OPTIONS = [ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
	value: String( level ),
	/* translators: %d: heading level, 2 to 6 */
	label: sprintf( __( 'Heading %d', 'parich-accessible-sitemap' ), level ),
} ) );

function EmptyPreview() {
	return (
		<Placeholder
			icon="list-view"
			label={ __(
				'Parich Accessible Sitemap',
				'parich-accessible-sitemap'
			) }
			instructions={ __(
				'There is nothing to list with these settings.',
				'parich-accessible-sitemap'
			) }
		/>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		only,
		sort,
		order,
		displayTitle,
		displayCategoryTitleWording,
		headingLevel,
		showToc,
		onlyPrivate,
		sectionTitles,
		postsPerCategory,
		collapsible,
	} = attributes;
	const sortOptions = getSortOptions( only );
	const renamableSections = displayTitle ? getRenamableSections( only ) : [];

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Content', 'parich-accessible-sitemap' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Show', 'parich-accessible-sitemap' ) }
						value={ only }
						options={ [
							...SECTION_OPTIONS,
							...postTypes,
							...taxonomies,
						] }
						// Sort values differ per list, so start again from the default order.
						onChange={ ( value ) =>
							setAttributes( {
								only: value,
								sort: '',
								order: '',
							} )
						}
					/>
					{ sortOptions && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Order by',
								'parich-accessible-sitemap'
							) }
							value={ sort }
							options={ sortOptions }
							onChange={ ( value ) =>
								setAttributes( { sort: value } )
							}
						/>
					) }
					{ only === 'post' && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Direction',
								'parich-accessible-sitemap'
							) }
							value={ order }
							options={ [
								{
									value: '',
									label: __(
										'Default',
										'parich-accessible-sitemap'
									),
								},
								{
									value: 'ASC',
									label: __(
										'Ascending',
										'parich-accessible-sitemap'
									),
								},
								{
									value: 'DESC',
									label: __(
										'Descending',
										'parich-accessible-sitemap'
									),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { order: value } )
							}
						/>
					) }
					{ ( only === '' || only === 'post' ) && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							min={ 0 }
							max={ 1000 }
							label={ __(
								'Posts per category',
								'parich-accessible-sitemap'
							) }
							help={ sprintf(
								/* translators: %d: posts per category in the plugin settings (0 = all) */
								__(
									'Empty: use the plugin setting (%d). 0: every post.',
									'parich-accessible-sitemap'
								),
								postsPerCategorySetting
							) }
							placeholder={ String( postsPerCategorySetting ) }
							value={
								postsPerCategory === undefined
									? ''
									: String( postsPerCategory )
							}
							onChange={ ( value ) =>
								setAttributes( {
									postsPerCategory:
										value === ''
											? undefined
											: Math.max(
													0,
													Math.min(
														1000,
														parseInt( value, 10 ) ||
															0
													)
												),
								} )
							}
						/>
					) }
					{ ( only === '' || only === 'post' ) && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Collapsible categories',
								'parich-accessible-sitemap'
							) }
							help={ __(
								'Visitors can fold each category away. Expanded is the safer start: search in page and screen readers reach every link.',
								'parich-accessible-sitemap'
							) }
							value={ collapsible }
							options={ [
								{
									value: '',
									label: __(
										'Off',
										'parich-accessible-sitemap'
									),
								},
								{
									value: 'open',
									label: __(
										'Expanded',
										'parich-accessible-sitemap'
									),
								},
								{
									value: 'closed',
									label: __(
										'Collapsed',
										'parich-accessible-sitemap'
									),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { collapsible: value } )
							}
						/>
					) }
					{ only === 'page' && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Private pages only',
								'parich-accessible-sitemap'
							) }
							help={ __(
								'Shown only to users who can read private pages.',
								'parich-accessible-sitemap'
							) }
							checked={ onlyPrivate }
							onChange={ ( value ) =>
								setAttributes( { onlyPrivate: value } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Headings', 'parich-accessible-sitemap' ) }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Section headings',
							'parich-accessible-sitemap'
						) }
						checked={ displayTitle }
						onChange={ ( value ) =>
							setAttributes( { displayTitle: value } )
						}
					/>
					{ displayTitle && (
						<>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Heading level',
									'parich-accessible-sitemap'
								) }
								help={ __(
									'Pick the level that follows the headings above the sitemap on the page.',
									'parich-accessible-sitemap'
								) }
								value={ String( headingLevel ) }
								options={ HEADING_OPTIONS }
								onChange={ ( value ) =>
									setAttributes( {
										headingLevel: parseInt( value, 10 ),
									} )
								}
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'“On this page” links',
									'parich-accessible-sitemap'
								) }
								help={ __(
									'Shown when there are two or more sections.',
									'parich-accessible-sitemap'
								) }
								checked={ showToc }
								onChange={ ( value ) =>
									setAttributes( { showToc: value } )
								}
							/>
						</>
					) }
					{ ( only === '' || only === 'post' ) && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Show “Category:” before category names',
								'parich-accessible-sitemap'
							) }
							checked={ displayCategoryTitleWording }
							onChange={ ( value ) =>
								setAttributes( {
									displayCategoryTitleWording: value,
								} )
							}
						/>
					) }
				</PanelBody>
				{ renamableSections.length > 0 && (
					<PanelBody
						title={ __(
							'Section titles',
							'parich-accessible-sitemap'
						) }
						initialOpen={ false }
					>
						{ renamableSections.map( ( section ) => (
							<TextControl
								key={ section.value }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ section.label }
								help={ __(
									'Leave empty to use the default title.',
									'parich-accessible-sitemap'
								) }
								placeholder={ section.label }
								value={ sectionTitles[ section.value ] || '' }
								onChange={ ( value ) =>
									setAttributes( {
										sectionTitles: {
											...sectionTitles,
											[ section.value ]: value,
										},
									} )
								}
							/>
						) ) }
					</PanelBody>
				) }
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ /* Links are inert in the editor; the real sitemap is on the published page. */ }
				<Disabled>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						EmptyResponsePlaceholder={ EmptyPreview }
					/>
				</Disabled>
			</div>
		</>
	);
}
