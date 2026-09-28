/**
 * Registers the parich-accessible-sitemap/sitemap block. The front end is rendered on the server (render.php).
 */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
} );
