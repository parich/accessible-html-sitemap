<?php
// This file is generated. Do not modify it manually.
return array(
	'sitemap' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'parich-accessible-sitemap/sitemap',
		'version' => '1.0.0',
		'title' => 'Parich Accessible Sitemap',
		'category' => 'widgets',
		'icon' => 'list-view',
		'description' => 'An accessible list of the site\'s pages, posts by category and other content, with jump links to each section.',
		'keywords' => array(
			'sitemap',
			'site map',
			'แผนผังเว็บไซต์'
		),
		'attributes' => array(
			'only' => array(
				'type' => 'string',
				'default' => ''
			),
			'sort' => array(
				'type' => 'string',
				'default' => ''
			),
			'order' => array(
				'type' => 'string',
				'default' => ''
			),
			'displayTitle' => array(
				'type' => 'boolean',
				'default' => true
			),
			'displayCategoryTitleWording' => array(
				'type' => 'boolean',
				'default' => true
			),
			'headingLevel' => array(
				'type' => 'number',
				'default' => 2
			),
			'showToc' => array(
				'type' => 'boolean',
				'default' => true
			),
			'onlyPrivate' => array(
				'type' => 'boolean',
				'default' => false
			),
			'sectionTitles' => array(
				'type' => 'object',
				'default' => array(
					
				)
			),
			'postsPerCategory' => array(
				'type' => 'number'
			),
			'collapsible' => array(
				'type' => 'string',
				'enum' => array(
					'',
					'open',
					'closed'
				),
				'default' => ''
			)
		),
		'styles' => array(
			array(
				'name' => 'tree',
				'label' => 'Tree',
				'isDefault' => true
			),
			array(
				'name' => 'list',
				'label' => 'List'
			)
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true
			)
		),
		'textdomain' => 'parich-accessible-sitemap',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	)
);
