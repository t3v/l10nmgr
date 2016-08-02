<?php
namespace Localizationteam\L10nmgr\Model;

/***************************************************************
 *  Copyright notice
 *  (c) 2006 Kasper Skårhøj <kasperYYYY@typo3.com>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Localizationteam\L10nmgr\Model\Tools\Tools;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * l10nAccumulatedInformations
 *  calculates accumulated informations for a l10n.
 *    Needs a tree object and a l10ncfg to work.
 *  This object is a value object (means it has no identity and can therefore be created and deleted “everywhere”).
 *  However this object should be generated by the relevant factory method in the l10nconfiguration object.
 * This object represents the relevant records which belongs to a l10ncfg in the concrete pagetree!
 *  The main method is the getInfoArrayForLanguage() which returns the $accum Array with the accumulated informations.
 *
 * @package TYPO3
 * @subpackage tx_l10nmgr
 */
class L10nAccumulatedInformation
{
    
    /**
     * @var string The status of this object, set to processed if internal variables are calculated.
     */
    var $objectStatus = 'new';
    /**
     * @var array Page tree
     */
    var $tree = array();
    /**
     * @var array Selected l10nmgr configuration
     */
    var $l10ncfg = array();
    /**
     * @var array List of not allowed doktypes
     */
    var $disallowDoktypes = array('--div--', '255');
    /**
     * @var int sys_language_uid of source language
     */
    var $sysLang;
    /**
     * @var int sys_language_uid of target language
     */
    var $forcedPreviewLanguage;
    /**
     * @var array Information about collected data for translation
     */
    var $_accumulatedInformations = array();
    /**
     * @var int Field count, might be needed by tranlation agencies
     */
    var $_fieldCount = 0;
    /**
     * @var int Word count, might be needed by tranlation agencies
     */
    var $_wordCount = 0;
    /**
     * @var array Extension's configuration as from the EM
     */
    protected $extensionConfiguration = array();
    
    /**
     * Constructor
     *
     * @param $tree
     * @param $l10ncfg
     * @param $sysLang
     */
    function __construct($tree, $l10ncfg, $sysLang)
    {
        // Load the extension's configuration
        $this->extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['l10nmgr']);
        $this->disallowDoktypes = GeneralUtility::trimExplode(',', $this->extensionConfiguration['disallowDoktypes']);
        
        $this->tree = $tree;
        $this->l10ncfg = $l10ncfg;
        $this->sysLang = $sysLang;
    }
    
    function setForcedPreviewLanguage($prevLangId)
    {
        $this->forcedPreviewLanguage = $prevLangId;
    }
    
    /**
     * return information array with accumulated information. This way client classes have access to the accumulated array directly. and can read this array in order to create some output...
     *
     * @return  array    Complete Information array
     */
    function getInfoArray()
    {
        $this->process();
        
        return $this->_accumulatedInformations;
    }
    
    function process()
    {
        if ($this->objectStatus != 'processed') {
            $this->_calculateInternalAccumulatedInformationsArray();
        }
        $this->objectStatus = 'processed';
    }
    
    /** set internal _accumulatedInformations array. Is called from constructor and uses the given tree, lang and l10ncfg
     *
     * @return void
     **/
    function _calculateInternalAccumulatedInformationsArray()
    {
        global $TCA;
        $tree = $this->tree;
        $l10ncfg = $this->l10ncfg;
        $accum = array();
        $sysLang = $this->sysLang;
        
        // FlexForm Diff data:
        $flexFormDiff = unserialize($l10ncfg['flexformdiff']);
        $flexFormDiff = $flexFormDiff[$sysLang];
        
        $excludeIndex = array_flip(GeneralUtility::trimExplode(',', $l10ncfg['exclude'], 1));
        $tableUidConstraintIndex = array_flip(GeneralUtility::trimExplode(',', $l10ncfg['tableUidConstraint'], 1));
        
        // Init:
        $t8Tools = GeneralUtility::makeInstance(Tools::class);
        $t8Tools->verbose = false; // Otherwise it will show records which has fields but none editable.
        if ($l10ncfg['incfcewithdefaultlanguage'] == 1) {
            $t8Tools->includeFceWithDefaultLanguage = true;
        }
        
        // Set preview language (only first one in list is supported):
        if ($this->forcedPreviewLanguage != '') {
            $previewLanguage = $this->forcedPreviewLanguage;
        } else {
            $previewLanguage = current(GeneralUtility::intExplode(',',
                $GLOBALS['BE_USER']->getTSConfigVal('options.additionalPreviewLanguages')));
        }
        if ($previewLanguage) {
            $t8Tools->previewLanguages = array($previewLanguage);
        }
        
        // Traverse tree elements:
        foreach ($tree->tree as $treeElement) {
            
            $pageId = $treeElement['row']['uid'];
            if (!isset($excludeIndex['pages:' . $pageId]) && !in_array($treeElement['row']['doktype'],
                    $this->disallowDoktypes)
            ) {
                
                $accum[$pageId]['header']['title'] = $treeElement['row']['title'];
                $accum[$pageId]['header']['icon'] = $treeElement['HTML'];
                $accum[$pageId]['header']['prevLang'] = $previewLanguage;
                $accum[$pageId]['items'] = array();
                
                // Traverse tables:
                foreach ($TCA as $table => $cfg) {
                    
                    // Only those tables we want to work on:
                    if (GeneralUtility::inList($l10ncfg['tablelist'], $table)) {
                        
                        if ($table === 'pages') {
                            $accum[$pageId]['items'][$table][$pageId] = $t8Tools->translationDetails('pages',
                                BackendUtility::getRecordWSOL('pages', $pageId), $sysLang, $flexFormDiff,
                                $previewLanguage);
                            $this->_increaseInternalCounters($accum[$pageId]['items'][$table][$pageId]['fields']);
                        } else {
                            $allRows = $t8Tools->getRecordsToTranslateFromTable($table, $pageId);
                            
                            if (is_array($allRows)) {
                                if (count($allRows)) {
                                    // Now, for each record, look for localization:
                                    foreach ($allRows as $row) {
                                        BackendUtility::workspaceOL($table, $row);
                                        if (is_array($row) && count($tableUidConstraintIndex) > 0) {
                                            if (is_array($row) && isset($tableUidConstraintIndex[$table . ':' . $row['uid']])) {
                                                $accum[$pageId]['items'][$table][$row['uid']] = $t8Tools->translationDetails($table,
                                                    $row, $sysLang, $flexFormDiff, $previewLanguage);
                                                $this->_increaseInternalCounters($accum[$pageId]['items'][$table][$row['uid']]['fields']);
                                            }
                                        } else {
                                            if (is_array($row) && !isset($excludeIndex[$table . ':' . $row['uid']])) {
                                                $accum[$pageId]['items'][$table][$row['uid']] = $t8Tools->translationDetails($table,
                                                    $row, $sysLang, $flexFormDiff, $previewLanguage);
                                                $this->_increaseInternalCounters($accum[$pageId]['items'][$table][$row['uid']]['fields']);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $includeIndex = array_unique(GeneralUtility::trimExplode(',', $l10ncfg['include'], 1));
        foreach ($includeIndex as $recId) {
            list($table, $uid) = explode(':', $recId);
            $row = BackendUtility::getRecordWSOL($table, $uid);
            if (count($row)) {
                $accum[-1]['items'][$table][$row['uid']] = $t8Tools->translationDetails($table, $row, $sysLang,
                    $flexFormDiff, $previewLanguage);
                $this->_increaseInternalCounters($accum[-1]['items'][$table][$row['uid']]['fields']);
            }
        }
        
        #		debug($accum);
        $this->_accumulatedInformations = $accum;
    }
    
    function _increaseInternalCounters($fieldsArray)
    {
        if (is_array($fieldsArray)) {
            $this->_fieldCount = $this->_fieldCount + count($fieldsArray);
            if (function_exists('str_word_count')) {
                foreach ($fieldsArray as $v) {
                    $this->_wordCount = $this->_wordCount + str_word_count($v['defaultValue']);
                }
            }
        }
    }
    
    function getFieldCount()
    {
        return $this->_fieldCount;
    }
    
    function getWordCount()
    {
        return $this->_wordCount;
    }
}