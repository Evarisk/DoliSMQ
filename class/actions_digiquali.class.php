<?php
/* Copyright (C) 2022 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_digiquali.class.php
 * \ingroup digiquali
 * \brief   DigiQuali hook overload.
 */

/**
 * Class ActionsDigiquali
 */
class ActionsDigiquali
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the constructCategory function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @return  int                             0 < on error, 0 on success, 1 to replace standard code
	 */
	public function constructCategory($parameters, &$object)
	{
		$error = 0; // Error counter

		if (strpos($parameters['context'], 'category') !== false) {
			$tags = [
				'question' => [
					'id'        => 436301001,
					'code'      => 'question',
					'obj_class' => 'Question',
					'obj_table' => 'digiquali_question',
				],
				'sheet' => [
					'id'        => 436301002,
					'code'      => 'sheet',
					'obj_class' => 'Sheet',
					'obj_table' => 'digiquali_sheet',
				],
				'control' => [
					'id'        => 436301003,
					'code'      => 'control',
					'obj_class' => 'Control',
					'obj_table' => 'digiquali_control',
				],
                'survey' => [
                    'id'        => 436301004,
                    'code'      => 'survey',
                    'obj_class' => 'Survey',
                    'obj_table' => 'digiquali_survey',
                ]
			];
		}

		if (!$error) {
			$this->results = $tags;
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param array $parameters Hook metadata (context, etc...)
     * @param object $object The object to process
     * @param string $action Current action (if set). Generally create or edit or null
     * @return int                0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function doActions(array $parameters, $object, string $action): int
    {
        global $conf, $user;

        if (strpos($parameters['context'], 'categorycard') !== false) {
            require_once __DIR__ . '/../class/question.class.php';
            require_once __DIR__ . '/../class/sheet.class.php';
            require_once __DIR__ . '/../class/control.class.php';
            require_once __DIR__ . '/../class/survey.class.php';
        }

        if (strpos($parameters['context'], 'productlotcard') !== false && $action == 'update_easy_url_link') {
            require_once DOL_DOCUMENT_ROOT . '/custom/easyurl/class/shortener.class.php';

            $productLot = new ProductLot($this->db);
            $shortener  = new Shortener($this->db);

            $id = GETPOSTINT('id');

            $productLot->fetch($id);
            $shortener->fetch('', '', ' AND t.status = ' . Shortener::STATUS_VALIDATED . ' OR (t.element_type = "productlot" AND t.fk_element = ' . $id . ')');

            if ($shortener->id > 0) {
                $shortener->element_type = 'productlot';
                $shortener->fk_element   = $productLot->id;
                $shortener->status       = Shortener::STATUS_ASSIGN;
                $shortener->type         = 0; // TODO : Changer ça pour mettre une vrai valeur du dico ?

                $objectData                = ['type' => $productLot->element, 'id' => $productLot->id];
                $objectB64                 = base64_encode(json_encode($objectData));
                $publicControlInterfaceUrl = dol_buildpath('custom/digiquali/public/control/public_control_history.php?track_id=' . $objectB64 . '&entity=' . $conf->entity, 3);
                $shortener->original_url   = $publicControlInterfaceUrl;

                update_easy_url_link($shortener);
                $shortener->update($user);

                $productLot->array_options['options_easy_url_all_link'] = $shortener->short_url;
                $productLot->updateExtraField('easy_url_all_link');

                setEventMessages('SetEasyURLSuccess', []);
            } else {
                // TODO : Faire en sorte de gerer le cas ou il n'y a pas de lien disponible
                setEventMessages('EasyURLNotAvailable', [], 'errors');
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the addHtmlHeader function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function addHtmlHeader(array $parameters): int
    {
        if (strpos($_SERVER['PHP_SELF'], 'digiquali') !== false) {
            ?>
            <script>
                $('link[rel="manifest"]').remove();
            </script>
            <?php

            $this->resprints = '<link rel="manifest" href="' . DOL_URL_ROOT . '/custom/digiquali/manifest.json.php' . '" />';
        }

        return 0; // or return 1 to replace standard code-->
    }

    /**
     * Overloading the printCommonFooter function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function printCommonFooter(array $parameters): int
    {
        global $conf, $langs;

        if (strpos($parameters['context'], 'categoryindex') !== false) {
            print '<script src="../custom/digiquali/js/digiquali.js"></script>';
        } elseif (strpos($parameters['context'], 'productlotcard') !== false) {
            $id  = GETPOSTINT('id');
            $out = '';

            if (isModEnabled('easyurl')) {
                require_once DOL_DOCUMENT_ROOT . '/custom/easyurl/class/shortener.class.php';

                $shortener = new Shortener($this->db);

                $shortener->fetch('', '', ' AND t.status = ' . Shortener::STATUS_ASSIGN . ' AND t.element_type = "productlot" AND t.fk_element = ' . $id);

                if ($shortener->id > 0) {
                    $publicControlInterfaceUrl = $shortener->short_url;

                    $out .= '<a href="' . $publicControlInterfaceUrl . '" target="_blank" title="URL : ' . $publicControlInterfaceUrl . '"><i class="fas fa-external-link-alt paddingrightonly"></i>' . ($publicControlInterfaceUrl) . '</a>';
                    $out .= showValueWithClipboardCPButton($publicControlInterfaceUrl, 0);
                } else {
                    $out .= '<a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=update_easy_url_link&token=' . newToken() . '">';
                    $out .= img_picto($langs->trans('SetEasyURLLink'), 'fontawesome_fa-redo_fas_#444', 'class="paddingright pictofixedwidth valignmiddle"') . '</a>';
                    $out .= '<span>' . img_picto($langs->trans('GetEasyURLErrors'), 'fontawesome_fa-exclamation-triangle_fas_#bc9526') . '</span>';
                }
//                    $output .= img_picto($langs->trans('SetEasyURLLink'), 'fontawesome_fa-redo_fas_#444', 'class="paddingright pictofixedwidth valignmiddle"') . '</a>';
//                    $out .= '<span>' . $langs->transnoentities('ObjectNotFound', $langs->transnoentities(ucfirst($shortener->element))) . '</span>';
//                    $out .= <button class="marginleftonly button_search self-end" type="submit"><span class="fas fa-redo-alt" style="font-size: 1em; color: grey;" title="' . $langs->transnoentities('Reload') . '"></span></button>';
//                }
            } else {
                $productLot = new ProductLot($this->db);

                $productLot->fetch($id);

                $objectData                = ['type' => $productLot->element, 'id' => $productLot->id];
                $objectB64                 = base64_encode(json_encode($objectData));
                $publicControlInterfaceUrl = dol_buildpath('custom/digiquali/public/control/public_control_history.php?track_id=' . $objectB64 . '&entity=' . $conf->entity, 3);

                $out .= '<a href="' . $publicControlInterfaceUrl . '" target="_blank" title="URL : ' . $publicControlInterfaceUrl . '"><i class="fas fa-external-link-alt paddingrightonly"></i>' . dol_trunc($publicControlInterfaceUrl) . '</a>';
                $out .= showValueWithClipboardCPButton($publicControlInterfaceUrl, 0);
            }

            ?>
            <script>
                $('[class*=extras_control_history_link]').html(<?php echo json_encode($out); ?>);
            </script>
            <?php
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param  array       $parameters Hook metadatas (context, etc...)
     * @param  object|null $object     Current object
     * @return int                     0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function formObjectOptions(array $parameters, ?object $object): int
    {
        global $extrafields, $langs;

        require_once __DIR__ . '/../lib/digiquali_sheet.lib.php';

        $linkableElements = get_sheet_linkable_objects();
        if (!empty($linkableElements)) {
            foreach($linkableElements as $linkableElement) {
                if ($linkableElement['tab_type'] == $object->element) {
                    if (strpos($parameters['context'], $linkableElement['hook_name_card']) !== false) {
                        $picto            = img_picto('', 'fontawesome_fa-clipboard-check_fas_#d35968', 'class="pictofixedwidth"');
                        $extraFieldsNames = ['qc_frequency', 'control_history_link'];
                        foreach ($extraFieldsNames as $extraFieldsName) {
                            $extrafields->attributes[$object->table_element]['label'][$extraFieldsName] = $picto . $langs->transnoentities($extrafields->attributes[$object->table_element]['label'][$extraFieldsName]);
                        }
                    }
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the printFieldListOption function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadata (context, etc...)
     * @param  CommonObject $object     Current object
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function printFieldListOption(array $parameters, $object): int
    {
        global $extrafields, $langs;

        require_once __DIR__ . '/../lib/digiquali_sheet.lib.php';

        $linkableElements = get_sheet_linkable_objects();
        if (!empty($linkableElements)) {
            foreach($linkableElements as $linkableElement) {
                if ($linkableElement['tab_type'] == $object->element) {
                    if (preg_match('/' . $linkableElement['hook_name_list'] . '|projecttaskscard/', $parameters['context'])) {
                        $picto            = img_picto('', 'fontawesome_fa-clipboard-check_fas_#d35968', 'class="pictofixedwidth"');
                        $extraFieldsNames = ['qc_frequency', 'control_history_link'];
                        foreach ($extraFieldsNames as $extraFieldsName) {
                            $extrafields->attributes[$object->table_element]['label'][$extraFieldsName] = $picto . $langs->transnoentities($extrafields->attributes[$object->table_element]['label'][$extraFieldsName]);
                        }
                    }
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

	/**
	 *  Overloading the redirectAfterConnection function : replacing the parent's function with the one below
	 *
	 * @param $parameters
	 * @return int
	 */
	public function redirectAfterConnection($parameters)
	{
		global $conf;

		if (strpos($parameters['context'], 'mainloginpage') !== false) {
			if ($conf->global->DIGIQUALI_REDIRECT_AFTER_CONNECTION) {
				$value = dol_buildpath('/custom/digiquali/digiqualiindex.php?mainmenu=digiquali', 1);
			} else {
				$value = '';
			}
		}

		if (true) {
			$this->resprints = $value;
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

    /**
     *  Overloading the saturneBannerTab function : replacing the parent's function with the one below.
     *
     * @param  array        $parameters Hook metadatas (context, etc...).
     * @param  CommonObject $object     Current object.
     * @return int                      0 < on error, 0 on success, 1 to replace standard code.
     */
    public function saturneBannerTab(array $parameters, CommonObject $object): int
    {
        global $conf, $langs;

        // Do something only for the current context.
        if (preg_match('/controlcard|surveycard/', $parameters['context'])) {
            if ($conf->browser->layout == 'phone') {
                $morehtmlref = '<br><div>' . img_picto('', 'fontawesome_fa-caret-square-down_far_#966EA2F2_fa-2em', 'class="toggle-object-infos pictofixedwidth valignmiddle" style="width: 35px;"') . $langs->trans('DisplayMoreInfo') . '</div>';
            } else {
                $morehtmlref = '';
            }

            $this->resprints = $morehtmlref;
        }

        return 0; // or return 1 to replace standard code.
    }

    /**
     * Overloading the saturneAdminDocumentData function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadatas (context, etc...).
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function saturneAdminDocumentData(array $parameters): int
    {
        // Do something only for the current context.
        if (strpos($parameters['context'], 'digiqualiadmindocuments') !== false) {
            $types = [
                'ControlDocument' => [
                    'documentType' => 'controldocument',
                    'picto'        => 'fontawesome_fa-tasks_fas_#d35968'
                ],
                'SurveyDocument' => [
                    'documentType' => 'surveydocument',
                    'picto'        => 'fontawesome_fa-marker_fas_#d35968'
                ]
            ];
            $this->results = $types;
        }

        return 0; // or return 1 to replace standard code.
    }

    /**
     * Overloading the saturneAdminObjectConst function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function saturneAdminObjectConst(array $parameters): int
    {
        if (strpos($parameters['context'], 'surveyadmin') !== false) {
            $constArray['digiquali'] = [
                'DisplayMedias' => [
                    'name'        => 'DisplayMediasSample',
                    'description' => 'DisplaySurveyMediasSampleDescription',
                    'code'        => 'DIGIQUALI_SURVEY_DISPLAY_MEDIAS',
                ],
                'UseLargeSizeMedia' => [
                    'name'        => 'UseLargeSizeMedia',
                    'description' => 'UseLargeSizeMediaDescription',
                    'code'        => 'DIGIQUALI_SURVEY_USE_LARGE_MEDIA_IN_GALLERY',
                ],
                'AutoSaveActionQuestionAnswer' => [
                    'name'        => 'AutoSaveActionQuestionAnswer',
                    'description' => 'AutoSaveActionQuestionAnswerDescription',
                    'code'        => 'DIGIQUALI_SURVEYDET_AUTO_SAVE_ACTION',
                ]
            ];
            $this->results = $constArray;
            return 1;
        }

        if (strpos($parameters['context'], 'controladmin') !== false) {
            $constArray['digiquali'] = [
                'DisplayMedias' => [
                    'name'        => 'DisplayMediasSample',
                    'description' => 'DisplayMediasSampleDescription',
                    'code'        => 'DIGIQUALI_CONTROL_DISPLAY_MEDIAS',
                ],
                'UseLargeSizeMedia' => [
                    'name'        => 'UseLargeSizeMedia',
                    'description' => 'UseLargeSizeMediaDescription',
                    'code'        => 'DIGIQUALI_CONTROL_USE_LARGE_MEDIA_IN_GALLERY',
                ],
                'LockControlOutdatedEquipment' => [
                    'name'        => 'LockControlOutdatedEquipment',
                    'description' => 'LockControlOutdatedEquipmentDescription',
                    'code'        => 'DIGIQUALI_LOCK_CONTROL_OUTDATED_EQUIPMENT',
                ],
                'AutoSaveActionQuestionAnswer' => [
                    'name'        => 'AutoSaveActionQuestionAnswer',
                    'description' => 'AutoSaveActionQuestionAnswerDescription',
                    'code'        => 'DIGIQUALI_CONTROLDET_AUTO_SAVE_ACTION',
                ],
                'EnablePublicControlHistory' => [
                    'name'        => 'EnablePublicControlHistory',
                    'description' => 'EnablePublicControlHistoryDescription',
                    'code'        => 'DIGIQUALI_ENABLE_PUBLIC_CONTROL_HISTORY',
                ],
                'ShowQcFrequencyPublicInterface' => [
                    'name'        => 'ShowQcFrequencyPublicInterface',
                    'description' => 'ShowQcFrequencyPublicInterfaceDescription',
                    'code'        => 'DIGIQUALI_SHOW_QC_FREQUENCY_PUBLIC_INTERFACE',
                ],
                'ShowLastControlFirstOnPublicHistory' => [
                    'name'        => 'ShowLastControlFirstOnPublicHistory',
                    'description' => 'ShowLastControlFirstOnPublicHistoryDescription',
                    'code'        => 'DIGIQUALI_SHOW_LAST_CONTROL_FIRST_ON_PUBLIC_HISTORY',
                ],
                'ShowAddControlButtonOnPublicInterface' => [
                    'name'        => 'ShowAddControlButtonOnPublicInterface',
                    'description' => 'ShowAddControlButtonOnPublicInterfaceDescription',
                    'code'        => 'DIGIQUALI_SHOW_ADD_CONTROL_BUTTON_ON_PUBLIC_INTERFACE',
                ]
            ];
            $this->results = $constArray;
            return 1;
        }

        return 0; // or return 1 to replace standard code.
    }
  
    function completeTabsHead(array $parameters) : int
    {
        global $langs, $conf;

        if (strpos($parameters['context'], 'main') !== false) {
            if (!empty($parameters['head'])) {
                foreach ($parameters['head'] as $headKey => $headTab) {
                    if (is_array($headTab) && count($headTab) > 0) {
                        if (isset($headTab[2]) && $headTab[2] === 'control' && is_string($headTab[1]) && strpos($headTab[1], $langs->trans('Controls')) !== false && strpos($headTab[1], 'badge') === false) {

                            $matches = [];
                            preg_match_all('/[?&](fromid|fromtype)=([^&]+)/', $headTab[0], $matches, PREG_SET_ORDER);
                            foreach ($matches as $match) {
                                if ($match[1] === 'fromid') {
                                    $fk_source = $match[2];
                                } elseif ($match[1] === 'fromtype') {
                                    $sourcetype = $match[2];
                                }
                            }
                            if (empty($fk_source) || empty($sourcetype)) {
                                continue;
                            }

                            require_once __DIR__ . '/../class/control.class.php';
                            $control = new Control($this->db);

                            $join = ' LEFT JOIN '. MAIN_DB_PREFIX . 'element_element as project ON (project.fk_source = ' . $fk_source . ' AND project.sourcetype = "' . $sourcetype . '" AND project.targettype = "'  . $control->table_element . '")';
                            $num  = saturne_fetch_all_object_type('Control', '', '', 0, 0, ['customsql' => 't.rowid = project.fk_target'], '', '', '', '', $join, ['count' => true]);

                            $parameters['head'][$headKey][1] .= '<span class="badge badge-pill badge-primary marginleftonlyshort">' . $num . '</span>';
                        }
                    }
                }
            }
        }

        return 0;
    }
}
