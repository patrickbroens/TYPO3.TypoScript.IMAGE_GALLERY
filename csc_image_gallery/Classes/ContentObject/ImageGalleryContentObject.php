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
	 * The defined width of the gallery
	 *
	 * @var int
	 */
	protected $definedWidth = 0;

	/**
	 * The calculated width of the gallery
	 *
	 * @var int
	 */
	protected $calculatedWidth = 0;

	/**
	 * TRUE when a border is used for the images in the gallery
	 *
	 * @var boolean
	 */
	protected $borderInUse = FALSE;

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

		$this->getFileObjects(\TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_IMAGE);
		$this->sortFileObjects();

		$this->calculateRowsAndColumns();

		$this->calculateImageWidthsAndHeights();

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
		$this->fileCount = count($this->fileObjects);
	}

	/**
	 * Do sorting for multiple file objects
	 *
	 * @return void
	 */
	protected function sortFileObjects() {
		$sortingProperty = '';
		if ($this->typoScriptConfiguration['sorting'] || $this->typoScriptConfiguration['sorting.']) {
			$sortingProperty = $this->contentObject->stdWrap(
				$this->typoScriptConfiguration['sorting'],
				$this->typoScriptConfiguration['sorting.']
			);
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
		$columns = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['columns.']['amount'],
				$this->typoScriptConfiguration['columns.']['amount.']
			)
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
	 * Calculate the width/height of the images
	 *
	 * Based on the width of the gallery, defined equal width or height by a user, the spacing between columns and
	 * the use of a border, defined by user, where the border width and padding are taken into account
	 *
	 * @return void
	 */
	protected function calculateImageWidthsAndHeights() {
		$galleryWidth = $this->definedWidth = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['width'],
				$this->typoScriptConfiguration['width.']
			)
		);

		$equalImageHeight = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['image.']['height'],
				$this->typoScriptConfiguration['image.']['height.']
			)
		);

		$equalImageWidth = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['image.']['width'],
				$this->typoScriptConfiguration['image.']['width.']
			)
		);

		$columnSpacing = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['columns.']['spacing'],
				$this->typoScriptConfiguration['columns.']['spacing.']
			)
		);

		$this->borderInUse = (boolean) $this->contentObject->stdWrap(
			$this->typoScriptConfiguration['image.']['border.']['on'],
			$this->typoScriptConfiguration['image.']['border.']['on.']
		);

		$borderWidth = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['image.']['border.']['width'],
				$this->typoScriptConfiguration['image.']['border.']['width.']
			)
		);

		$borderPadding = intval(
			$this->contentObject->stdWrap(
				$this->typoScriptConfiguration['image.']['border.']['padding'],
				$this->typoScriptConfiguration['image.']['border.']['padding.']
			)
		);

		$columnSpacingTotal = ($this->columns - 1) * $columnSpacing;

		$galleryWidthMinusBorderAndSpacing = $galleryWidth - $columnSpacingTotal;

		if ($this->borderInUse) {
			$borderPaddingTotal = ($this->columns * 2) * $borderPadding;
			$borderWidthTotal = ($this->columns * 2) * $borderWidth;
			$galleryWidthMinusBorderAndSpacing = $galleryWidthMinusBorderAndSpacing - $borderPaddingTotal - $borderWidthTotal;
		}

			// User entered a predefined height
		if ($equalImageHeight) {
			$imageScalingCorrection = 1;
			$maximumRowWidth = 0;

				// Calculate the scaling correction when the total of images is wider than the gallery width
			for ($row = 1; $row <= $this->rows; $row++) {
				$totalRowWidth = 0;
				for ($column = 1; $column <= $this->columns; $column++) {
					$fileKey = (($row - 1) * $this->columns) + $column - 1;
					if ($fileKey > $this->fileCount - 1) {
						break 2;
					}
					$currentImageScaling = $equalImageHeight / $this->fileObjects[$fileKey]->getProperty('height');
					$totalRowWidth += $this->fileObjects[$fileKey]->getProperty('width') * $currentImageScaling;
				}
				$maximumRowWidth = max($totalRowWidth, $maximumRowWidth);
				$imagesInRowScaling = $totalRowWidth / $galleryWidthMinusBorderAndSpacing;
				$imageScalingCorrection = max($imagesInRowScaling, $imageScalingCorrection);
			}

				// Set the corrected dimensions for each image
			foreach ($this->fileObjects as $key => $fileObject) {
				$imageHeight = floor($equalImageHeight / $imageScalingCorrection);
				$imageWidth = floor(
					$fileObject->getProperty('width') * ($imageHeight / $fileObject->getProperty('height'))
				);
				$this->imageDimensions[$key] = array(
					'width' => 	$imageWidth,
					'height' => $imageHeight
				);
			}

			$this->calculatedWidth = floor($maximumRowWidth / $imageScalingCorrection) + $galleryWidthMinusBorderAndSpacing;

			// User entered a predefined width
		} elseif ($equalImageWidth) {
			$imageScalingCorrection = 1;

				// Calculate the scaling correction when the total of images is wider than the gallery width
			$totalRowWidth = $this->columns * $equalImageWidth;
			$imagesInRowScaling = $totalRowWidth / $galleryWidthMinusBorderAndSpacing;
			$imageScalingCorrection = max($imagesInRowScaling, $imageScalingCorrection);

				// Set the corrected dimensions for each image
			foreach ($this->fileObjects as $key => $fileObject) {
				$imageWidth = floor($equalImageWidth / $imageScalingCorrection);
				$imageHeight = floor(
					$fileObject->getProperty('height') * ($imageWidth / $fileObject->getProperty('width'))
				);
				$this->imageDimensions[$key] = array(
					'width' => 	$imageWidth,
					'height' => $imageHeight
				);
			}

			$this->calculatedWidth = floor($totalRowWidth / $imageScalingCorrection) + $galleryWidthMinusBorderAndSpacing;

			// Automatic setting of width and height
		} else {
			$imageWidth = intval($galleryWidthMinusBorderAndSpacing / $this->columns);

			foreach ($this->fileObjects as $key => $fileObject) {
				$imageHeight = floor(
					$fileObject->getProperty('height') * ($imageWidth / $fileObject->getProperty('width'))
				);
				$this->imageDimensions[$key] = array(
					'width' => 	$imageWidth,
					'height' => $imageHeight
				);
			}

			$this->calculatedWidth =  $galleryWidth;
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

		$GLOBALS['TSFE']->register['FILES_COUNT'] = $this->fileCount;
		$GLOBALS['TSFE']->register['GALLERY_COLUMNS_COUNT'] = $this->columns;
		$GLOBALS['TSFE']->register['GALLERY_ROWS_COUNT'] = $this->rows;

		$splitConfiguration = $GLOBALS['TSFE']->tmpl->splitConfArray(
			$this->typoScriptConfiguration,
			count($this->fileObjects)
		);

		for ($row = 1; $row <= $this->rows; $row++) {
			for ($column = 1; $column <= $this->columns; $column++) {

				$fileKey = (($row - 1) * $this->columns) + $column - 1;

				$GLOBALS['TSFE']->register['IMAGE_NUM_CURRENT'] = $fileKey;
				$GLOBALS['TSFE']->register['GALLERY_CURRENT_COLUMN'] = $column;
				$GLOBALS['TSFE']->register['GALLERY_CURRENT_ROW'] = $row;
				$GLOBALS['TSFE']->register['GALLERY_CURRENT_IMAGE_WIDTH'] = $this->imageDimensions[$fileKey]['width'];
				$GLOBALS['TSFE']->register['GALLERY_CURRENT_IMAGE_HEIGHT'] = $this->imageDimensions[$fileKey]['height'];

				$GLOBALS['TSFE']->register['GALLERY_ROW_ITERATION_FIRST'] = $row === 1 ? TRUE : FALSE;
				$GLOBALS['TSFE']->register['GALLERY_ROW_ITERATION_LAST'] = $row === $this->rows ? TRUE : FALSE;

				$GLOBALS['TSFE']->register['GALLERY_COLUMN_ITERATION_FIRST'] = $column === 1 ? TRUE : FALSE;
				$GLOBALS['TSFE']->register['GALLERY_COLUMN_ITERATION_LAST'] = $column === $this->columns ? TRUE : FALSE;

					// Needed when there are more images in one column, like with option noRows (currently not taken into account)
				$GLOBALS['TSFE']->register['GALLERY_IMAGE_ITERATION_FIRST'] = TRUE;
				$GLOBALS['TSFE']->register['GALLERY_IMAGE_ITERATION_LAST'] = TRUE;

					// If the next file object does not exist, this is the last image
				if (!$this->fileObjects[$fileKey + 1]) {
					$GLOBALS['TSFE']->register['GALLERY_ROW_ITERATION_LAST'] = TRUE;
					$GLOBALS['TSFE']->register['GALLERY_COLUMN_ITERATION_LAST'] = TRUE;
				}

				$this->contentObject->setCurrentFile($this->fileObjects[$fileKey]);

				$content .= $this->contentObject->cObjGetSingle(
					$splitConfiguration[$fileKey]['renderObj'],
					$splitConfiguration[$fileKey]['renderObj.']
				);
			}
		}

		$content = $this->contentObject->stdWrap($content, $this->typoScriptConfiguration);

		return $content;
	}
}