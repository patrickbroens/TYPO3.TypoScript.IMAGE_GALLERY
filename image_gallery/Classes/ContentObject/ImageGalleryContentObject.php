<?php
namespace TYPO3\CMS\Frontend\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Patrick Broens <patrick@patrickbroens.nl>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The IMAGE_GALLERY content object
 */
class ImageGalleryContentObject {

	/**
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $contentObject;

	/**
	 * @var array
	 */
	protected $typoScriptConfiguration = array();

	/**
	 * @var array
	 */
	protected $fileObjects = array();

	/**
	 * The amount of files
	 *
	 * @var int
	 */
	protected $fileCount = 0;

	/**
	 * The amount of rows
	 *
	 * @var int
	 */
	protected $rows = 0;

	/**
	 * The amount of columns
	 *
	 * @var int
	 */
	protected $columns = 0;

	/**
	 * The dimensions of each image
	 *
	 * @var array
	 */
	protected $imageDimensions = array();

	/**
	 * Renders the application defined cObject IMAGE_GALLERY
	 *
	 * @param string $typoScriptObjectName Name of the object
	 * @param array $typoScriptConfiguration TS configuration for this cObject
	 * @param string $typoScriptKey A string label used for the internal debugging tracking.
	 * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObject reference
	 * @return string HTML output
	 */
	public function cObjGetSingleExt(
		$typoScriptObjectName,
		array $typoScriptConfiguration,
		$typoScriptKey,
		\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObject
	) {
		$this->contentObject = $contentObject;
		$this->typoScriptConfiguration = $typoScriptConfiguration;

		$this->getFileObjects();
		$this->sortFileObjects();
		$GLOBALS['TSFE']->register['FILES_COUNT'] = $this->fileCount = count($this->fileObjects);

		$this->calculateRowsAndColumns();
		$GLOBALS['TSFE']->register['GALLERY_COLUMNS_COUNT'] = $this->columns;
		$GLOBALS['TSFE']->register['GALLERY_ROWS_COUNT'] = $this->rows;

		$this->calculateImageWidths();

		$content = $this->renderFilesThroughRenderObj();
		$content = $this->contentObject->stdWrap($content, $this->typoScriptConfiguration['stdWrap.']);

		return $content;
	}

	/**
	 * Get the file objects from the resources
	 *
	 * @return void
	 */
	protected function getFileObjects() {
		$fileResource = GeneralUtility::makeInstance(
			'TYPO3\CMS\Frontend\ContentObject\Utility\FileResource',
			$this->contentObject,
			$this->typoScriptConfiguration
		);
		$this->fileObjects = $fileResource->getFileObjects();
	}

	/**
	 * Do sorting for multiple file objects
	 *
	 * @return void
	 */
	protected function sortFileObjects() {
		$sortingProperty = '';
		if ($this->typoScriptConfiguration['sorting'] || $this->typoScriptConfiguration['sorting.']) {
			$sortingProperty = $this->stdWrapValue('sorting', $this->typoScriptConfiguration);
		}
		if ($sortingProperty !== '' && count($this->fileObjects) > 1) {
			usort(
				$this->fileObjects,
				function(
					\TYPO3\CMS\Core\Resource\FileInterface $a,
					\TYPO3\CMS\Core\Resource\FileInterface $b
				) use($sortingProperty) {
					if ($a->hasProperty($sortingProperty) && $b->hasProperty($sortingProperty)) {
						return strnatcasecmp($a->getProperty($sortingProperty), $b->getProperty($sortingProperty));
					} else {
						return 0;
					}
				}
			);
		}
	}

	/**
	 * Calculate the rows and columns
	 *
	 * @return void
	 */
	protected function calculateRowsAndColumns() {
			// Get the amount of columns from Typoscript
		$columns = intval($this->contentObject->stdWrap(
			$this->typoScriptConfiguration['columns'],
			$this->typoScriptConfiguration['columns.'])
		);

			// If no columns defined, set it to 1
		$columns = $columns > 1 ? $columns : 1;

			// When more columns than images, set the columns to the amount of images
		if ($columns > $this->fileCount) {
			$columns = $this->fileCount;
		}

			// Calculate the rows from the amount of files and the columns
		$rows = ceil($this->fileCount / $columns);

			// Get the amount of rows from TypoScript
		$rowsDefined = intval($this->contentObject->stdWrap(
			$this->typoScriptConfiguration['rows'],
			$this->typoScriptConfiguration['rows.'])
		);

			// If the rows are defined in TypoScript, the columns need to be recalculated
		if ($rowsDefined > 1) {
			$rows = $rowsDefined;
			if ($rows > $this->fileCount) {
				$rows = $this->fileCount;
			}
			if ($rows > 1) {
				$columns = ceil($this->fileCount / $rows);
			} else {
				$columns = $this->fileCount;
			}
		}

		$this->columns = $columns;
		$this->rows = $rows;
	}

	/**
	 * Calculate the width of the images
	 *
	 * Currently simple, not taking into account
	 * - equalH (needs gifbuilder for each image, so we need to return an array for the width of each image)
	 * - colSpace
	 * - border
	 * - borderThick
	 * - borderSpace
	 */
	protected function calculateImageWidths() {
		$galleryWidth = intval($this->contentObject->stdWrap(
			$this->typoScriptConfiguration['width'],
			$this->typoScriptConfiguration['width.'])
		);

		$imageWidth = intval($galleryWidth / $this->columns);

		foreach ($this->fileObjects as $key => $fileObject) {
			$this->imageDimensions[$key] = array(
				'width' => 	$imageWidth
			);
		}
	}

	/**
	 * Render each file though the renderObj
	 *
	 * optionSplit will be applied to allow different settings for each file
	 *
	 * @return string HTML content of all files
	 */
	protected function renderFilesThroughRenderObj() {
		$content = '';

		$splitConfiguration = $GLOBALS['TSFE']->tmpl->splitConfArray(
			$this->typoScriptConfiguration,
			count($this->fileObjects)
		);

		$fileObjectCounter = 0;

		foreach ($this->fileObjects as $key => $fileObject) {
			$GLOBALS['TSFE']->register['IMAGE_NUM_CURRENT'] = $fileObjectCounter;
			$GLOBALS['TSFE']->register['GALLERY_CURRENT_COLUMN'] = $fileObjectCounter % $this->columns + 1;
			$GLOBALS['TSFE']->register['GALLERY_CURRENT_ROW'] = intval($fileObjectCounter / $this->columns + 1);
			$GLOBALS['TSFE']->register['GALLERY_CURRENT_IMAGE_WIDTH'] = $this->imageDimensions[$key]['width'];

			$this->contentObject->setCurrentFile($fileObject);

			$content .= $this->contentObject->cObjGetSingle(
				$splitConfiguration[$key]['renderObj'],
				$splitConfiguration[$key]['renderObj.']
			);

			$fileObjectCounter++;
		}

		return $content;
	}
}