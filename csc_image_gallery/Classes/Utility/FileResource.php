<?php
namespace TYPO3\CMS\Frontend\ContentObject\Utility;

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

class FileResource {

	/**
	 * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	protected $contentObject;

	/**
	 * @var \TYPO3\CMS\Core\Resource\FileRepository
	 */
	protected $fileRepository;

	/**
	 * @var array
	 */
	protected $typoScriptConfiguration = array();

	/**
	 * @var \TYPO3\CMS\Core\Log\Logger
	 */
	protected $logger;

	/**
	 * @var array
	 */
	protected $fileObjects = array();

	/**
	 * @var integer
	 */
	protected $fileTypeFilter;

	/**
	 * Default constructor.
	 *
	 * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObject
	 */
	public function __construct(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObject, array $typoScriptConfiguration) {
		$this->contentObject = $contentObject;
		$this->typoScriptConfiguration = $typoScriptConfiguration;
		$this->fileRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
		$this->logger = GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger();
	}

	/**
	 * Returns file objects from references, files, collections and folders
	 * According to the TypoScript settings
	 *
	 * @param integer $fileType Filter for file type
	 * @return array
	 */
	public function getFileObjects($fileType = NULL) {
		if ($fileType) {
			$this->fileTypeFilter = (integer) $fileType;
		}

		$this->addFileObjectsFromReferences();
		$this->addFileObjectsFromFiles();
		$this->addFileObjectsFromCollections();
		$this->addFileObjectsFromFolders();

		return $this->fileObjects;
	}

	/**
	 * Add the files from references to the file objects
	 *
	 * @return void
	 */
	protected function addFileObjectsFromReferences() {
		if ($this->typoScriptConfiguration['references'] || $this->typoScriptConfiguration['references.']) {
			/*
			The TypoScript could look like this:# all items related to the page.media field:
			references {
			table = pages
			uid.data = page:uid
			fieldName = media
			}# or: sys_file_references with uid 27:
			references = 27
			 */
			$referencesUid = $this->stdWrapValue('references', $this->typoScriptConfiguration);
			$referencesUidArray = GeneralUtility::intExplode(',', $referencesUid, TRUE);
			foreach ($referencesUidArray as $referenceUid) {
				try {
					$this->addToFileObjects($this->fileRepository->findFileReferenceByUid($referenceUid));
				} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
					$this->logger->warning('The file-reference with uid  "' . $referenceUid . '" could not be found and won\'t be included in frontend output');
				}
			}

			// It's important that this always stays "fieldName" and not be renamed to "field" as it would otherwise collide with the stdWrap key of that name
			$referencesFieldName = $this->stdWrapValue('fieldName', $this->typoScriptConfiguration['references.']);
			if ($referencesFieldName) {
				$table = $this->contentObject->getCurrentTable();
				if ($table === 'pages' && isset($this->contentObject->data['_LOCALIZED_UID']) && intval($this->contentObject->data['sys_language_uid']) > 0) {
					$table = 'pages_language_overlay';
				}
				$referencesForeignTable = $this->stdWrapValue('table', $this->typoScriptConfiguration['references.'], $table);
				$referencesForeignUid = $this->stdWrapValue('uid', $this->typoScriptConfiguration['references.'], isset($this->contentObject->data['_LOCALIZED_UID']) ? $this->contentObject->data['_LOCALIZED_UID'] : $this->contentObject->data['uid']);
				$this->addToFileObjects($this->fileRepository->findByRelation($referencesForeignTable, $referencesFieldName, $referencesForeignUid));
			}
		}
	}

	/**
	 * Add the files from files to the file objects
	 *
	 * @return void
	 */
	protected function addFileObjectsFromFiles() {
		if ($this->typoScriptConfiguration['files'] || $this->typoScriptConfiguration['files.']) {
			/*
			The TypoScript could look like this:
			# with sys_file UIDs:
			files = 12,14,15# using stdWrap:
			files.field = some_field
			 */
			$fileUids = GeneralUtility::trimExplode(',', $this->stdWrapValue('files', $this->typoScriptConfiguration), TRUE);
			foreach ($fileUids as $fileUid) {
				try {
					$this->addToFileObjects($this->fileRepository->findByUid($fileUid));
				} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
					$this->logger->warning('The file with uid  "' . $fileUid . '" could not be found and won\'t be included in frontend output');
				}
			}
		}
	}

	/**
	 * Add the files from collections to the file objects
	 *
	 * @return void
	 */
	protected function addFileObjectsFromCollections() {
		if ($this->typoScriptConfiguration['collections'] || $this->typoScriptConfiguration['collections.']) {
			$collectionUids = GeneralUtility::trimExplode(',', $this->stdWrapValue('collections', $this->typoScriptConfiguration), TRUE);
			/** @var \TYPO3\CMS\Core\Resource\FileCollectionRepository $collectionRepository */
			$collectionRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileCollectionRepository');
			foreach ($collectionUids as $collectionUid) {
				try {
					$fileCollection = $collectionRepository->findByUid($collectionUid);
					if ($fileCollection instanceof \TYPO3\CMS\Core\Resource\Collection\AbstractFileCollection) {
						$fileCollection->loadContents();
						$this->addToFileObjects($fileCollection->getItems());
					}
				} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
					$this->logger->warning('The file-collection with uid  "' . $collectionUid . '" could not be found or contents could not be loaded and won\'t be included in frontend output');
				}
			}
		}
	}

	/**
	 * Add the files from folders to the file objects
	 *
	 * @return void
	 */
	protected function addFileObjectsFromFolders() {
		if ($this->typoScriptConfiguration['folders'] || $this->typoScriptConfiguration['folders.']) {
			$folderIdentifiers = GeneralUtility::trimExplode(',', $this->stdWrapValue('folders', $this->typoScriptConfiguration));
			/** @var \TYPO3\CMS\Core\Resource\ResourceFactory $fileFactory */
			$fileFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\ResourceFactory');
			foreach ($folderIdentifiers as $folderIdentifier) {
				if ($folderIdentifier) {
					try {
						$folder = $fileFactory->getFolderObjectFromCombinedIdentifier($folderIdentifier);
						if ($folder instanceof \TYPO3\CMS\Core\Resource\Folder) {
							$this->addToFileObjects($folder->getFiles());
						}
					} catch (\TYPO3\CMS\Core\Resource\Exception $e) {
						$this->logger->warning('The folder with identifier  "' . $folderIdentifier . '" could not be found and won\'t be included in frontend output');
					}
				}
			}
		}
	}

	/**
	 * Adds $newItems to file objects
	 *
	 * Takes the file type filter into account
	 *
	 * @param mixed $newItems Array with new items or single object that's added.
	 */
	protected function addToFileObjects($newItems) {
		if (is_array($newItems)) {
			foreach ($newItems as $item) {
				$this->addToFileObjects($item);
			}
		} elseif (is_object($newItems)) {
			if ($this->fileTypeFilter === NULL || $newItems->getType() === $this->fileTypeFilter) {
				$this->fileObjects[] = $newItems;
			}
		}
	}

	/**
	 * Gets a configuration value by passing them through stdWrap first and taking a default value if stdWrap doesn't yield a result.
	 *
	 * @param string $key The config variable key (from TS array).
	 * @param array $this->typoScriptConfigurationig The TypoScript array.
	 * @param string $defaultValue Optional default value.
	 * @return string Value of the config variable
	 */
	protected function stdWrapValue($key, array $typoScriptConfiguration, $defaultValue = '') {
		$stdWrapped = $this->contentObject->stdWrap($typoScriptConfiguration[$key], $typoScriptConfiguration[$key . '.']);
		return $stdWrapped ? $stdWrapped : $defaultValue;
	}
}