<?php

/***************************************************************
 *  Copyright notice
 *
 *  Copyright (c) 2009, AOE media GmbH <dev@aoemedia.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * The exporter is responsible to export a set of pages as xml files
 *
 * class.tx_l10nmgr_models_exporter_Exporter.php
 *
 * @author	 Timo Schmidt <schmidt@aoemedia.de>
 * @copyright Copyright (c) 2009, AOE media GmbH <dev@aoemedia.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version $Id: class.class_name.php $
 * @date 01.04.2009 - 15:11:03
 * @package	TYPO3
 * @subpackage	extensionkey
 * @access public
 */
class tx_l10nmgr_models_exporter_exporter {

	/**
	 * @var tx_l10nmgr_models_exporter_exportData
	 */
	protected $exportData;

	/**
	 * @var int
	 */
	protected $numberOfPagesPerChunck;

	/**
	 * @var boolean
	 */
	protected $isChunkProcessed;


	/**
	 * Constructor to create an instance of the exporter object
	 *
	 * @param tx_l10nmgr_models_exporter_exportData $exportData
	 */
	public function __construct(tx_l10nmgr_models_exporter_exportData $exportData, $numberOfPagesPerChunk){
		$this->exportData 				= $exportData;
		$this->numberOfPagesPerChunk  	= $numberOfPagesPerChunk;
		$this->isChunkProcessed			= false;
	}

	public function run(){

		if(!$this->exportData->getIsCompletelyProcessed()){
			$pagesForChunk 				= $this->getNextPagesChunk();

			$l10ncfgObj					= $this->exportData->getL10nConfigurationObject();
			$targetLanguage				= $this->exportData->getTranslationLanguageObject();
			$sourceLanguage				= $this->exportData->getSourceLanguageObject();

			$factory 					= new tx_l10nmgr_models_translateable_translateableInformationFactory();
			$tranlateableInformation 	= $factory->create($l10ncfgObj,$pagesForChunk,$targetLanguage,$sourceLanguage);

			$viewClassName=t3lib_div::makeInstanceClassName('tx_l10nmgr_CATXMLView');
			$viewClass=new $viewClassName($l10ncfgObj,$tranlateableInformation);
			$viewClass->setForcedSourceLanguage($sourceLanguage);

			echo $viewClass->render();

			$this->removeProcessedChunkPages($pagesForChunk);
			$this->setIsChunkProcessed(true);

			return true;
		}else{
			return false;
		}
	}

	/**
	 * @return boolean
	 */
	protected function getIsChunkProcessed() {
		return $this->isChunkProcessed;
	}

	/**
	 * @param boolean $isChunkProcessed
	 */
	protected function setIsChunkProcessed($isChunkProcessed) {
		$this->isChunkProcessed = $isChunkProcessed;
	}

	/**
	 * Retuns the internal exportDataObject
	 *
	 * @return tx_l10nmgr_models_exporter_exportData
	 */
	public function getExportData(){
		if(!$this->getIsChunkProcessed()){
			throw new LogicException('it makes no sence to read the export data from an unprocessed run');
		}else{
			return $this->exportData;
		}
	}



	/**
	 * Builds a chunck of pageIds from the set of remaining pages of an export
	 *
	 * @return ArrayObject
	 */
	protected function getNextPagesChunk(){
		$allPages 			= $this->exportData->getRemainingPages();
		$chunk				= new ArrayObject();

		$allPagesIterator 	= $allPages->getIterator();
		for($pagesInChunk = 0; $pagesInChunk < $this->getNumberOfPagesPerChunk(); $pagesInChunk++){
			if($allPagesIterator->valid()){
				$chunk->append($allPagesIterator->current());
				$allPagesIterator->next();
			}
		}

		return $chunk;
	}

	/**
	 * Returns the configuration option for the number of pages per chunck.
	 *
	 * @return int
	 */
	protected function getNumberOfPagesPerChunk(){
		return $this->numberOfPagesPerChunk;
	}

	/**
	 * Method removes a set of pages from the remaining pages in the exportData
	 *
	 * @param ArrayObject $pageIdCollection
	 */
	protected function removeProcessedChunkPages($pageIdCollection){
		$this->exportData->removePagesIdsFromRemainingPages($pageIdCollection);
	}
}

?>