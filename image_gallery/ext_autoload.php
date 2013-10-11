<?php
/*
 * Register necessary class names with autoloader
 */
$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('image_gallery');

return array(
	'TYPO3\CMS\Frontend\ContentObject\Utility\FileResource' => $extensionPath . 'Classes/Utility/FileResource.php'
);