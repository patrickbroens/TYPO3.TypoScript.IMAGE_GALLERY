<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][] = array(
	'IMAGE_GALLERY',
	'EXT:image_gallery/Classes/ContentObject/ImageGalleryContentObject.php:&TYPO3\\CMS\\Frontend\\ContentObject\\ImageGalleryContentObject'
);