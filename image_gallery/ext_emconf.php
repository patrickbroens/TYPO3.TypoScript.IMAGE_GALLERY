<?php

########################################################################
# Extension Manager/Repository config file for ext "image_gallery".
#
# Auto generated 08-02-2012 20:49
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'IMAGE_GALLERY TS content object',
	'description' => 'Proof of concept for having an image gallery in TS using FAL images',
	'category' => 'frontend',
	'shy' => 0,
	'version' => '1.0.0',
	'dependencies' => 'cms',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'experimental',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Patrick Broens',
	'author_email' => 'patrick@patrickbroens.nl',
	'author_company' => 'Patrick Broens',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-6.2.99',
			'php' => '5.3.0-0.0.0',
			'cms' => '6.2.0-6.2.99',
			'css_styled_content' => '6.2.0-6.2.99'
		),
		'conflicts' => array(
		),
		'suggests' => array(
		)
	),
	'_md5_values_when_last_written' => ''
);