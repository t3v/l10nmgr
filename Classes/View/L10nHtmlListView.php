<?php
namespace Localizationteam\L10nmgr\View;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Kasper Skårhøj <kasperYYYY@typo3.com>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Template\DocumentTemplate;

/**
 * l10nHTMLListView:
 *  renders accumulated informations for the browser:
 *  - Table with inline editing / links  etc...
 *
 *
 * @author  Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author  Daniel Pötzinger <development@aoemedia.de>
 *
 * @package TYPO3
 * @subpackage tx_l10nmgr
 */
class L10nHtmlListView extends AbstractExportView
{

    var $l10ncfgObj; //
    var $sysLang; // Internal array (=datarow of config record)

    //internal flags:
    var $modeWithInlineEdit = false;

    var $modeShowEditLinks = false;

    function __construct($l10ncfgObj, $sysLang)
    {
        global $BACK_PATH;
        $this->doc = GeneralUtility::makeInstance(DocumentTemplate::class);
        $this->doc->backPath = $BACK_PATH;
        parent::__construct($l10ncfgObj, $sysLang);
    }

    function setModeWithInlineEdit()
    {
        $this->modeWithInlineEdit = true;
    }

    function setModeShowEditLinks()
    {
        $this->modeShowEditLinks = true;
    }

    /**
     * Render the module content in HTML
     *
     * @param  array    Translation data for configuration
     * @param  integer    Sys language uid
     * @param  array    Configuration record
     * @return  string    HTML content
     */
    function renderOverview()
    {

        global $LANG;

        $sysLang = $this->sysLang;
        $accumObj = $this->l10ncfgObj->getL10nAccumulatedInformationsObjectForLanguage($sysLang);
        $accum = $accumObj->getInfoArray();
        $l10ncfg = $this->l10ncfg;

        $output = '';

        $showSingle = GeneralUtility::_GET('showSingle');

        if ($l10ncfg['displaymode'] > 0) {
            $showSingle = $showSingle ? $showSingle : 'NONE';
            if ($l10ncfg['displaymode'] == 2) {
                $noAnalysis = true;
            }
        } else {
            $noAnalysis = false;
        }

        // Traverse the structure and generate HTML output:
        foreach ($accum as $pId => $page) {
            $output .= '<h3>' . $page['header']['icon'] . htmlspecialchars($page['header']['title']) . ' [' . $pId . ']</h3>';

            $tableRows = array();

            foreach ($accum[$pId]['items'] as $table => $elements) {
                foreach ($elements as $elementUid => $data) {
                    if (is_array($data['fields'])) {

                        $FtableRows = array();
                        $flags = array();

                        if (!$noAnalysis || $showSingle === $table . ':' . $elementUid) {
                            foreach ($data['fields'] as $key => $tData) {
                                if (is_array($tData)) {
                                    list(, $uidString, $fieldName) = explode(':', $key);
                                    list($uidValue) = explode('/', $uidString);

                                    $diff = '';
                                    $edit = true;
                                    $noChangeFlag = !strcmp(trim($tData['diffDefaultValue']),
                                        trim($tData['defaultValue']));
                                    if ($uidValue === 'NEW') {
                                        $diff = '<em>' . $LANG->getLL('render_overview.new.message') . '</em>';
                                        $flags['new']++;
                                    } elseif (!isset($tData['diffDefaultValue'])) {
                                        $diff = '<em>' . $LANG->getLL('render_overview.nodiff.message') . '</em>';
                                        $flags['unknown']++;
                                    } elseif ($noChangeFlag) {
                                        $diff = $LANG->getLL('render_overview.nochange.message');
                                        $edit = true;
                                        $flags['noChange']++;
                                    } else {
                                        $diff = $this->diffCMP($tData['diffDefaultValue'], $tData['defaultValue']);
                                        $flags['update']++;
                                    }

                                    if (!$this->modeOnlyChanged || !$noChangeFlag) {
                                        $fieldCells = array();
                                        $fieldCells[] = '<b>' . htmlspecialchars($fieldName) . '</b>' . ($tData['msg'] ? '<br/><em>' . htmlspecialchars($tData['msg']) . '</em>' : '');
                                        $fieldCells[] = nl2br(htmlspecialchars($tData['defaultValue']));
                                        $fieldCells[] = $edit && $this->modeWithInlineEdit ? ($tData['fieldType'] === 'text' ? '<textarea name="' . htmlspecialchars('translation[' . $table . '][' . $elementUid . '][' . $key . ']') . '" cols="60" rows="5">' . GeneralUtility::formatForTextarea($tData['translationValue']) . '</textarea>' : '<input name="' . htmlspecialchars('translation[' . $table . '][' . $elementUid . '][' . $key . ']') . '" value="' . htmlspecialchars($tData['translationValue']) . '" size="60" />') : nl2br(htmlspecialchars($tData['translationValue']));
                                        $fieldCells[] = $diff;

                                        if ($page['header']['prevLang'] && is_array($tData['previewLanguageValues'])) {
                                            reset($tData['previewLanguageValues']);
                                            $fieldCells[] = nl2br(htmlspecialchars(current($tData['previewLanguageValues'])));
                                        }

                                        $FtableRows[] = '<tr class="db_list_normal"><td>' . implode('</td><td>',
                                                $fieldCells) . '</td></tr>';
                                    }
                                }
                            }
                        }

                        if (count($FtableRows) || $noAnalysis) {

                            // Link:
                            if ($this->modeShowEditLinks) {
                                if (is_array($data['fields'])) {
                                    reset($data['fields']);
                                }
                                list(, $uidString) = explode(':', key($data['fields']));
                                if (substr($uidString, 0, 3) !== 'NEW') {
                                    $editId = is_array($data['translationInfo']['translations'][$sysLang]) ? $data['translationInfo']['translations'][$sysLang]['uid'] : $data['translationInfo']['uid'];
                                    $editLink = ' - <a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick('&edit[' . $data['translationInfo']['translation_table'] . '][' . $editId . ']=edit',
                                            $this->doc->backPath)) . '"><em>[' . $LANG->getLL('render_overview.clickedit.message') . ']</em></a>';
                                } else {
                                    $editLink = ' - <a href="' . htmlspecialchars($this->doc->issueCommand(
                                            '&cmd[' . $table . '][' . $data['translationInfo']['uid'] . '][localize]=' . $sysLang
                                        )) . '"><em>[' . $LANG->getLL('render_overview.clicklocalize.message') . ']</em></a>';
                                }
                            } else {
                                $editLink = '';
                            }

                            $tableRows[] = '<tr class="t3-row-header">
								<td colspan="2" style="width:300px;"><a href="' . htmlspecialchars('index.php?id=' . GeneralUtility::_GET('id') . '&showSingle=' . rawurlencode($table . ':' . $elementUid)) . '">' . htmlspecialchars($table . ':' . $elementUid) . '</a>' . $editLink . '</td>
								<td colspan="3" style="width:200px;">' . htmlspecialchars(GeneralUtility::arrayToLogString($flags)) . '</td>
							</tr>';

                            if (!$showSingle || $showSingle === $table . ':' . $elementUid) {
                                $tableRows[] = '<tr class="bgColor-20 tableheader">
									<td>Fieldname:</td>
									<td width="25%">Default:</td>
									<td width="25%">Translation:</td>
									<td width="25%">Diff:</td>
									' . ($page['header']['prevLang'] ? '<td width="25%">PrevLang:</td>' : '') . '
								</tr>';

                                $tableRows = array_merge($tableRows, $FtableRows);
                            }
                        }
                    }
                }
            }

            if (count($tableRows)) {
                $output .= '<table class="typo3-dblist" border="0" cellpadding="0" cellspacing="0">' . implode('',
                        $tableRows) . '</table>';
            }
        }

        return $output;
    }
}