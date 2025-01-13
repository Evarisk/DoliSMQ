<?php
/* Copyright (C) 2022-2024 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       view/control/control_card.php
 *		\ingroup    digiquali
 *		\brief      Page to create/edit/view control
 */

// Load DigiQuali environment
if (file_exists('../digiquali.main.inc.php')) {
    require_once __DIR__ . '/../digiquali.main.inc.php';
} elseif (file_exists('../../digiquali.main.inc.php')) {
    require_once __DIR__ . '/../../digiquali.main.inc.php';
} else {
    die('Include of digiquali main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmdirectory.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

require_once __DIR__ . '/../../class/control.class.php';
require_once __DIR__ . '/../../class/sheet.class.php';
require_once __DIR__ . '/../../class/question.class.php';
require_once __DIR__ . '/../../class/answer.class.php';
require_once __DIR__ . '/../../class/digiqualidocuments/controldocument.class.php';
require_once __DIR__ . '/../../lib/digiquali_control.lib.php';
require_once __DIR__ . '/../../lib/digiquali_answer.lib.php';
require_once __DIR__ . '/../../lib/digiquali_sheet.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['other', 'bills', 'orders']);

// Get parameters
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$subaction           = GETPOST('subaction', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'controlcard'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$source              = GETPOST('source', 'alpha'); // source PWA
$viewmode            = (GETPOSTISSET('viewmode') ? GETPOST('viewmode', 'alpha') : 'list'); // view mode for new control

// Initialize objects
// Technical objets
$object           = new Control($db);
$objectLine       = new ControlLine($db);
$document         = new ControlDocument($db);
$signatory        = new SaturneSignature($db, 'digiquali');
$controlEquipment = new ControlEquipment($db);
$product          = new Product($db);
$sheet            = new Sheet($db);
$question         = new Question($db);
$answer           = new Answer($db);
$usertmp          = new User($db);
$thirdparty       = new Societe($db);
$contact          = new Contact($db);
$extrafields      = new ExtraFields($db);
$ecmfile          = new EcmFiles($db);
$ecmdir           = new EcmDirectory($db);
$category         = new Categorie($db);

// View objects
$form = new Form($db);

$hookmanager->initHooks(array('controlcard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$searchAll = GETPOST('search_all', 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
    if (GETPOST('search_' . $key, 'alpha')) $search[$key] = GETPOST('search_' . $key, 'alpha');
}

if (empty($action) && empty($id) && empty($ref)) $action = 'view';

// Load object
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

$permissiontoread       = $user->rights->digiquali->control->read;
$permissiontoadd        = $user->rights->digiquali->control->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete     = $user->rights->digiquali->control->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
$permissiontosetverdict = $user->rights->digiquali->control->setverdict;
$upload_dir = $conf->digiquali->multidir_output[isset($object->entity) ? $object->entity : 1];

// Security check - Protection if external user
saturne_check_access($permissiontoread, $object);

/*
 * Actions
 */

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks.
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    $error = 0;

    $backurlforlist = dol_buildpath('/digiquali/view/control/control_list.php?source=' . $source, 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) $backtopage = $backurlforlist;
            else $backtopage = dol_buildpath('/digiquali/view/control/control_card.php', 1) . '?id=' . ($id > 0 ? $id : '__ID__') . '&source=' . $source;
        }
    }

	// Action clone object
	if ($action == 'confirm_clone' && $confirm == 'yes') {
        $options['label']      = GETPOST('clone_label');
        $options['attendants'] = GETPOST('clone_attendants');
        $options['photos']     = GETPOST('clone_photos');
        if ($object->id > 0) {
            $result = $object->createFromClone($user, $object->id, $options);
            if ($result > 0) {
                header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $result);
                exit();
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    if ($action == 'add' && !$cancel) {
        $linkableElements = get_sheet_linkable_objects();
        $controlledObjectSelected = 0;

        if (!empty($linkableElements)) {
            foreach ($linkableElements as $linkableElementType => $linkableElement) {
                if (!empty(GETPOST($linkableElement['post_name'])) && GETPOST($linkableElement['post_name']) > 0) {
                    $controlledObjectSelected++;
                }
            }
        }

        if (GETPOST('fk_sheet') > 0) {
            if ($controlledObjectSelected == 0) {
                setEventMessages($langs->trans('NeedObjectToControl'), [], 'errors');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?action=create&fk_sheet=' . GETPOST('fk_sheet') . '&viewmode=' . $viewmode . '&source=' . $source);
                exit;
            }
        } else {
            setEventMessages($langs->trans('NeedFkSheet'), [], 'errors');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?action=create&viewmode=' . $viewmode . '&source=' . $source);
            exit;
        }
    }

    // Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
    include DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';

    // Actions set_thirdparty, set_project
    require_once __DIR__ . '/../../../saturne/core/tpl/actions/banner_actions.tpl.php';

    if ($action == 'set_categories' && $permissiontoadd) {
        if ($object->fetch($id) > 0) {
            $result = $object->setCategories(GETPOST('categories', 'array'));
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id);
            exit();
        }
    }

    if ($action == 'show_only_questions_with_no_answer') {
        $data = json_decode(file_get_contents('php://input'), true);

        $showOnlyQuestionsWithNoAnswer = $data['showOnlyQuestionsWithNoAnswer'];

        $tabParam['DIGIQUALI_SHOW_ONLY_QUESTIONS_WITH_NO_ANSWER'] = $showOnlyQuestionsWithNoAnswer;

        dol_set_user_param($db, $conf, $user, $tabParam);
    }

    require_once __DIR__ . '/../../core/tpl/digiquali_answers_save_action.tpl.php';

    // Actions builddoc, forcebuilddoc, remove_file.
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/documents_action.tpl.php';

    // Action to generate pdf from odt file
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/saturne_manual_pdf_generation_action.tpl.php';

    if ($action == 'confirm_setVerdict' && $permissiontosetverdict && !GETPOST('cancel', 'alpha')) {
        $object->fetch($id);
        if (!$error) {
            $object->verdict = GETPOST('verdict', 'int');
            $object->note_public .= (!empty($object->note_public) ? chr(0x0A) : '') . GETPOST('noteControl');
            $result = $object->update($user);
            if ($result > 0) {
                // Set verdict Control
                $object->call_trigger('CONTROL_VERDICT', $user);
                $urltogo = str_replace('__ID__', $result, $backtopage);
                $urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
                header('Location: ' . $urltogo);
                exit;
            } else {
                // Set verdict Control error
                if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
                else setEventMessages($object->error, null, 'errors');
            }
        }
    }

    // Action to set status STATUS_REOPENED
    if ($action == 'confirm_set_reopen') {
        $object->fetch($id);
        if (!$error) {
            $result = $object->setDraft($user, false);
            if ($result > 0) {
                $object->verdict = null;
                $result = $object->update($user);
                // Set reopened OK
                $urltogo = str_replace('__ID__', $result, $backtopage);
                $urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
                header('Location: ' . $urltogo);
                exit;
            } else {
                // Set reopened KO
                if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
                else setEventMessages($object->error, null, 'errors');
            }
        }
    }

    // Actions confirm_lock, confirm_archive
    require_once __DIR__ . '/../../../saturne/core/tpl/actions/object_workflow_actions.tpl.php';

    // Actions to send emails
    $triggersendname = 'CONTROL_SENTBYMAIL';
    $autocopy        = 'MAIN_MAIL_AUTOCOPY_AUDIT_TO';
    $trackid         = 'control' . $object->id;
    include DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
}

/*
 * View
 */

$title    = $langs->trans('Control');
$help_url = 'FR:Module_DigiQuali';
$moreJS   = ['/saturne/js/includes/hammer.min.js'];

if ($source == 'pwa') {
    $conf->dol_hide_topmenu  = 1;
    $conf->dol_hide_leftmenu = 1;
}

saturne_header(1,'', $title, $help_url, '', 0, 0, $moreJS);
$object->fetch(GETPOST('id'));

$elementArray = get_sheet_linkable_objects();

// Part to create
if ($action == 'create') {
    $moreHtmlRight  = '<a class="btnTitle butActionNew ' . (($viewmode == 'list') ? '' : 'btnTitleSelected') . '" href="' . $_SERVER['PHP_SELF'] . '?action=create&viewmode=images&source=' . $source . '"><span class="fas fa-3x fa-images valignmiddle paddingleft" title="' . $langs->trans('ViewModeImages') . '"></span></a>';
    $moreHtmlRight .= '<a class="btnTitle butActionNew ' . (($viewmode == 'list') ? 'btnTitleSelected' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?action=create&viewmode=list&source=' . $source . '"><span class="fas fa-3x fa-list valignmiddle paddingleft" title="' . $langs->trans('ViewModeList') . '"></span></a>';
    print load_fiche_titre($langs->trans('NewControl'), $moreHtmlRight, 'object_' . $object->picto);

    print '<form method="POST" id="createObjectForm" action="' . $_SERVER['PHP_SELF'] . '?viewmode=' . $viewmode . '&source=' . $source . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="add">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
    }
    if ($backtopageforcancel) {
        print '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
    }
    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldcreate control-table">';

    $object->fields['fk_user_controller']['default'] = $user->id;

    if (!empty(GETPOST('fk_sheet'))) {
        $sheet->fetch(GETPOST('fk_sheet'));
        $_POST['label'] = $sheet->label;
    }

    if ($viewmode == 'images') {
        if (!getDolGlobalInt('DIGIQUALI_SHEET_MAIN_CATEGORIES_SET')) {
            print '<div class="wpeo-notice notice-warning notice-red">';
            print '<div class="notice-content">';
            print '<a href="' . dol_buildpath('/custom/digiquali/admin/sheet.php#sheetCategories', 2) . '">' . '<b><div class="notice-subtitle">' . $langs->trans('GenerateSheetTags') . ' : ' . $langs->trans('ConfigSheet') . '</div></b></a>';
            print '</div></div>';
            print '</table>';
            print dol_get_fiche_end();
            print '</form>';
            exit;
        }

        print '<div class="sheet-images-container">';
        print '<div class="titre center">' . $langs->trans('SheetCategories') . '</div>';
        print '<div class="sheet-grid-images sheet-categories">';
        $category->fetch($conf->global->DIGIQUALI_SHEET_MAIN_CATEGORY);
        $mainCategories = $category->get_filles();
        if (is_array($mainCategories) && !empty($mainCategories)) {
            foreach ($mainCategories as $mainCategory) {
                saturne_show_category_image($mainCategory, 0, 'photo-sheet-category');
            }
        }
        print '</div>';
        if (GETPOSTISSET('sheetCategoryID')) {
            $category->fetch(GETPOST('sheetCategoryID'));
            $mainSubCategories = $category->get_filles();
            if (is_array($mainSubCategories) && !empty($mainSubCategories)) {
                print '<div class="titre center">' . $langs->trans('SheetSubCategories') . '</div>';
                print '<div class="sheet-grid-images sheet-sub-categories">';
                foreach ($mainSubCategories as $mainSubCategory) {
                    saturne_show_category_image($mainSubCategory, 0, 'photo-sheet-sub-category');
                }
                print '</div>';
            }
        }
        print '<div class="titre center">' . $langs->trans('Sheet') . '</div>';
        print '<div class="sheet-grid-images sheet-elements">';
        print '<input type="hidden" name="fk_sheet" value="' . GETPOST('fk_sheet') . '">';
        if (GETPOSTISSET('sheetCategoryID') || (GETPOSTISSET('sheetSubCategoryID') && GETPOST('sheetSubCategoryID') != 'undefined')) {
            $sheets = saturne_fetch_all_object_type('Sheet', '', '', 0, 0, ['customsql' => 'cp.fk_categorie = ' . ((GETPOSTISSET('sheetSubCategoryID') && GETPOST('sheetSubCategoryID') != 'undefined') ? GETPOST('sheetSubCategoryID') : GETPOST('sheetCategoryID'))], 'AND', false, true, true);
            if (is_array($sheets) && !empty($sheets)) {
                foreach ($sheets as $sheetSingle) {
                    print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/sheet/' . $sheetSingle->ref . '/photos/', 'small', '', 0, 0, 0, 50, 50, 1, 1, 0, 'sheet/' . $sheetSingle->ref . '/photos/', $sheetSingle, '', 0, 0, 0, 0, 'photo-sheet');
                }
            }
        }
        print '</div></div>';
    } else {
        //FK SHEET
        $objectTypeArray = (!empty(GETPOST('fromtype')) ? saturne_get_objects_metadata(GETPOST('fromtype')) : []);
        $filterType      = (!empty($objectTypeArray) ? dol_strtolower($objectTypeArray['name']) : '');
        $filter          = 's.type = ' . '"' . $object->element . '" AND s.status = ' . Sheet::STATUS_LOCKED;
        $filter         .= (!empty($filterType) ? ' AND s.element_linked LIKE "%' . $filterType . '%"' : '');
        print '<tr><td class="fieldrequired">' . ($source != 'pwa' ? $langs->trans('Sheet') : img_picto('', $sheet->picto . '_2em', 'class="pictofixedwidth"')) . '</td><td>';
        print ($source != 'pwa' ? img_picto('', $sheet->picto, 'class="pictofixedwidth"') : '') . $sheet->selectSheetList(GETPOST('fk_sheet') ?: $sheet->id, 'fk_sheet', $filter, 0);
        if ($source != 'pwa') {
            print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/custom/digiquali/view/sheet/sheet_card.php?action=create" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddSheet') . '"></span></a>';
        }
        print '</td></tr>';
    }

    if ($source == 'pwa') {
        $object->fields['fk_user_controller']['type']  = 'integer:User:user/class/user.class.php';
        $object->fields['fk_user_controller']['label'] = img_picto('', 'fontawesome_fa-user_fas_#79633f_2em', 'class="pictofixedwidth"');
        $object->fields['fk_user_controller']['picto'] = '';
        $object->fields['projectid']['type']           = 'integer:Project:projet/class/project.class.php';
        $object->fields['projectid']['label']          = img_picto('', 'fontawesome_fa-project-diagram_fas_#6c6aa8_2em', 'class="pictofixedwidth"');
        $object->fields['projectid']['picto']          = '';
    }

    // Common attributes
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

    // Categories
    if (!empty($conf->categorie->enabled)) {
        print '<tr><td>' . ($source != 'pwa' ? $langs->trans('Categories') : img_picto('', 'fontawesome_fa-tags_fas_#000000_2em', 'class="pictofixedwidth"')) . '</td><td>';
        $categoryArborescence = $form->select_all_categories('control', '', 'parent', 64, 0, 1);
        print ($source != 'pwa' ? img_picto('', 'category', 'class="pictofixedwidth"') : '') . $form::multiselectarray('categories', $categoryArborescence, GETPOST('categories', 'array'), '', 0, 'minwidth100imp maxwidth500 widthcentpercentminusxx');
        if ($source != 'pwa') {
            print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/categories/index.php?type=control&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?action=create') . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddCategories') . '"></span></a>';
        }
        print '</td></tr>';
    }

    print '</table>';
    print '<hr>';

    print '<table class="centpercent tableforfieldcreate object-table linked-objects">';

    print '<tr><td>';
    print '<div class="fields-content">';

    foreach($elementArray as $linkableElementType => $linkableElement) {
        if (!empty($linkableElement['conf'] && (!empty(GETPOST('fromtype')) && GETPOST('fromtype') == $linkableElement['link_name']) || (preg_match('/"'. $linkableElementType .'":1/',$sheet->element_linked)))) {
            $objectArray    = [];
            $objectPostName = $linkableElement['post_name'];
            $objectPost     = GETPOST($objectPostName) ?: (GETPOST('fromtype') == $linkableElement['link_name'] ? GETPOST('fromid') : '');

            if ((dol_strlen($linkableElement['fk_parent']) > 0 && GETPOST($linkableElement['parent_post']) > 0)) {
                $objectFilter = ['customsql' => $linkableElement['fk_parent'] . ' = ' . GETPOST($linkableElement['parent_post'])];
            } else {
                $objectFilter = [];
            }
            $objectList = saturne_fetch_all_object_type($linkableElement['className'], '', '', 0, 0, $objectFilter);

            if (is_array($objectList) && !empty($objectList)) {
                foreach ($objectList as $objectSingle) {
                    $objectName = '';
                    $nameField = $linkableElement['name_field'];
                    if (strstr($nameField, ',')) {
                        $nameFields = explode(', ', $nameField);
                        if (is_array($nameFields) && !empty($nameFields)) {
                            foreach ($nameFields as $subnameField) {
                                $objectName .= $objectSingle->$subnameField . ' ';
                            }
                        }
                    } else {
                        $objectName = $objectSingle->$nameField;
                    }
                    $objectArray[$objectSingle->id] = $objectName;
                }
            }

            print '<tr><td class="titlefieldcreate">' . ($source != 'pwa' ? $langs->transnoentities($linkableElement['langs']) : img_picto('', $linkableElement['picto'], 'class="pictofixedwidth fa-3x"')) . '</td><td>';
            print($source != 'pwa' ? img_picto('', $linkableElement['picto'], 'class="pictofixedwidth"') : '');
            print $form->selectArray($objectPostName, $objectArray, $objectPost, $langs->trans('Select') . ' ' . strtolower($langs->trans($linkableElement['langs'])), 0, 0, '', 0, 0, dol_strlen(GETPOST('fromtype')) > 0 && GETPOST('fromtype') != $linkableElement['link_name'], '', 'maxwidth500 widthcentpercentminusxx');
            if ($source != 'pwa') {
                print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/' . $linkableElement['create_url'] . '?action=create&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?action=create') . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('Create') . ' ' . strtolower($langs->trans($linkableElement['langs'])) . '"></span></a>';
            }
            print '</td></tr>';
        }
    }

    print '</div>';

    // Other attributes
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Create', 'Cancel', [], 0, 'wpeo-button');

    print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
    $object->fetch_optionals();

    saturne_get_fiche_head($object, 'card', $title);
    saturne_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '', !empty($object->photo));

    $sheet->fetch($object->fk_sheet);
    $sheet->fetchObjectLinked($object->fk_sheet, 'digiquali_' . $sheet->element, null, '', 'OR', 1, 'position');
    $questionIds = $sheet->linkedObjectsIds['digiquali_question'];

    $questionCounter = 0;
    if (!empty($questionIds)) {
        $questionCounter = count($questionIds);
    }

    $answerCounter = 0;
    if (is_array($object->lines) && !empty($object->lines)) {
        foreach ($object->lines as $objectLine) {
            if (dol_strlen($objectLine->answer) > 0) {
                $answerCounter++;
            }
        }
    }

    $formConfirm = '';

    // If conf activated we check that at least one control equipment is outdated
    $equipmentOutdated = false;
    if (getDolGlobalInt('DIGIQUALI_LOCK_CONTROL_OUTDATED_EQUIPMENT')) {
        $controlEquipments = $controlEquipment->fetchFromParent($object->id);
        if (is_array($controlEquipments) && !empty($controlEquipments)) {
            foreach ($controlEquipments as $equipmentControl) {
                $data = json_decode($equipmentControl->json, true);
                $dluo = $data['dluo'];
                if (!empty($dluo) && $dluo <= dol_now()) {
                    $equipmentOutdated = true;
                    break;
                }
            }
        }
    }

    if (($action == 'setVerdict' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formquestion = [
            ['type' => 'select', 'name' => 'verdict',     'label' => '<span class="fieldrequired">' . $langs->trans('VerdictControl') . '</span>', 'values' => ['1' => 'OK', '2' => 'KO'], 'select_show_empty' => 0],
            ['type' => 'text',   'name' => 'noteControl', 'label' => '<div class="note-control" style="margin-top: 20px;">' . $langs->trans('NoteControl') . '</div>']
        ];

        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('SetOK/KO'), $langs->transnoentities('BeCarefullVerdictKO'), 'confirm_setVerdict', $formquestion, 'yes', 'actionButtonVerdict', 300);
    }

    // Validate confirmation
    if (($action == 'validate' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $questionConfirmInfo = $langs->trans('YouAnswered') . ' ' . $answerCounter . ' ' . $langs->trans('question(s)')  . ' ' . $langs->trans('On') . ' ' . $questionCounter . '.';
        if ($questionCounter - $answerCounter != 0) {
            $questionConfirmInfo .= '<br><b>' . $langs->trans('BewareQuestionsAnswered', $questionCounter - $answerCounter) . '</b>';
        }

        $questionConfirmInfo .= '<br><br><b>' . $langs->trans('ConfirmValidateControl') . '</b>';
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ValidateControl'), $questionConfirmInfo, 'confirm_validate', '', 'yes', 'actionButtonValidate', 250);
    }

    // Draft confirmation
    if (($action == 'draft' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&object_type=' . $object->element, $langs->trans('ReOpenObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmReOpenObject', $langs->transnoentities('The' . ucfirst($object->element)), $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_set_reopen', '', 'yes', 'actionButtonInProgress', 350, 600);
    }

    // Lock confirmation
    if (($action == 'lock' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('LockObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmLockObject', $langs->transnoentities('The' . ucfirst($object->element))) . ($object->verdict == 2 ? '<br>' . $langs->transnoentities('BeCarefullVerdictKO') : ''), 'confirm_lock', '', 'yes', 'actionButtonLock', 350, 600);
    }

    // Clone confirmation
    if (($action == 'clone' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        // Define confirmation messages
        $formQuestionClone = [
            ['type' => 'text',     'name' => 'clone_label', 'label' => $langs->trans('NewLabelForClone', $langs->transnoentities('The' . ucfirst($object->element))), 'value' => $langs->trans('CopyOf') . ' ' . $object->label ?: $object->ref, 'size' => 24],
            ['type' => 'checkbox', 'name' => 'clone_attendants', 'label' => $langs->trans('CloneAttendants'), 'value' => 1],
            ['type' => 'checkbox', 'name' => 'clone_photos',     'label' => $langs->trans('ClonePhotos'),     'value' => 1]
        ];

        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('CloneObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmCloneObject', $langs->transnoentities('The' . ucfirst($object->element)), $object->ref), 'confirm_clone', $formQuestionClone, 'yes', 'actionButtonClone', 350, 600);
    }

    // Delete confirmation
    if ($action == 'delete') {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmDeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_delete', '', 'yes', 1);
    }

    // Call Hook formConfirm
    $parameters = ['formConfirm' => $formConfirm];
    $resHook    = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
    if (empty($resHook)) {
        $formConfirm .= $hookmanager->resPrint;
    } elseif ($resHook > 0) {
        $formConfirm = $hookmanager->resPrint;
    }

    // Print form confirm
    print $formConfirm;

    if ($conf->browser->layout == 'phone') {
        $onPhone = 1;
    } else {
        $onPhone = 0;
    }

    print '<div class="fichecenter object-infos' . ($onPhone ? ' hidden' : '') . '">';
    print '<div class="fichehalfleft">';
    print '<table class="border centpercent tableforfield">';

    // Common attributes
    unset($object->fields['projectid']); // Hide field already shown in banner

    if (getDolGlobalInt('SATURNE_ENABLE_PUBLIC_INTERFACE')) {
        $publicInterfaceUrl = dol_buildpath('custom/digiquali/public/control/public_control.php?track_id=' . $object->track_id . '&entity=' . $conf->entity, 3);
        print '<tr><td class="titlefield">' . $langs->trans('PublicInterface') . ' <a href="' . $publicInterfaceUrl . '" target="_blank"><i class="fas fa-qrcode"></i></a>';
        print showValueWithClipboardCPButton($publicInterfaceUrl, 0, '&nbsp;');
        print '</td>';
        print '<td>' . saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/control/' . $object->ref . '/qrcode/', 'small', 1, 0, 0, 0, 80, 80, 0, 0, 0, 'control/' . $object->ref . '/qrcode/', $object, '', 0, 0) . '</td></tr>';

        // Answer public interface
        $publicAnswerUrl = dol_buildpath('custom/digiquali/public/public_answer.php?track_id=' . $object->track_id . '&object_type=' . $object->element . '&document_type=ControlDocument&entity=' . $conf->entity, 3);
        print '<tr><td class="titlefield">' . $langs->trans('PublicAnswer') . ' <a href="' . $publicAnswerUrl . '" target="_blank"><i class="fas fa-qrcode"></i></a>';
        print showValueWithClipboardCPButton($publicAnswerUrl, 0, '&nbsp;');
        print '</td><td>';
        print '<a href="' . $publicAnswerUrl . '" target="_blank">' . $langs->trans('GoToPublicAnswerPage') . ' <i class="fa fa-external-link"></a>';
        print '</td></tr>';
    }

    print '<tr class="field_control_date"><td class="titlefield fieldname_control_date">';
    print $form->editfieldkey('ControlDate', 'control_date', $object->control_date, $object, $permissiontoadd && $object->status < Control::STATUS_LOCKED, 'datepicker');
    print '</td><td class="valuefield fieldname_control_date">';
    print $form->editfieldval('ControlDate', 'control_date', $object->control_date, $object, $permissiontoadd && $object->status < Control::STATUS_LOCKED, 'datepicker', '', null, null, "id=$object->id");
    print '</td>';

    print '<tr class="field_next_control_date"><td class="titlefield fieldname_next_control_date">';
    print $form->editfieldkey('NextControlDate', 'next_control_date', $object->next_control_date, $object, $permissiontoadd && $object->status < Control::STATUS_LOCKED, 'datepicker');
    print '</td><td class="valuefield fieldname_next_control_date">';
    print $form->editfieldval('NextControlDate', 'next_control_date', $object->next_control_date, $object, $permissiontoadd && $object->status < Control::STATUS_LOCKED, 'datepicker', '', null, null, "id=$object->id");
    print '</td>';

    print '<tr class="field_verdict"><td class="titlefield fieldname_verdict">';
    print $langs->trans('Verdict');
    print '</td><td class="valuefield fieldname_verdict">';
    $verdictColor = $object->verdict == 1 ? 'green' : ($object->verdict == 2 ? 'red' : 'grey');
    print dol_strlen($object->verdict) > 0 ? '<div class="wpeo-button button-' . $verdictColor . '">' . $object->fields['verdict']['arrayofkeyval'][(!empty($object->verdict)) ? $object->verdict : 3] . '</div>' : 'N/A';
    print '</td>';

    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

    // Categories
    if (isModEnabled('categorie')) {
        print '<tr><td class="valignmiddle">' . $langs->trans('Categories') . '</td>';
        if ($action != 'categories') {
            print '<td style="display: flex">' . ($object->status < Control::STATUS_LOCKED ? '<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=categories&id=' . $object->id . '">' . img_edit($langs->trans('Modify')) . '</a>' : '<img src="" alt="">');
            print $form->showCategories($object->id, 'control', 1) . '</td>';
        }
        if ($permissiontoadd && $action == 'categories') {
            $categoryArborescence = $form->select_all_categories('control', '', 'parent', 64, 0, 1);
            $categoryArborescence = empty($categoryArborescence) ? [] : $categoryArborescence;
            if (is_array($categoryArborescence)) {
                print '<td>';
                print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="post">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="set_categories">';

                $cats          = $category->containing($object->id, 'control');
                $arraySelected = [];
                if (is_array($cats)) {
                    foreach ($cats as $cat) {
                        $arraySelected[] = $cat->id;
                    }
                }
                print img_picto('', 'category') . $form->multiselectarray('categories', $categoryArborescence, (GETPOSTISSET('categories') ? GETPOST('categories', 'array') : $arraySelected), '', 0, 'minwidth100imp quatrevingtpercent widthcentpercentminusx');
                print '<input type="submit" class="button button-edit small" value="' . $langs->trans('Save') . '">';
                print '</form>';
                print '</td>';
            }
        }
        print '</tr>';
    }

    $object->fetchObjectLinked('', '', $object->id, 'digiquali_control', 'OR', 1, 'sourcetype', 0);

    foreach ($elementArray as $linkableElementType => $linkableElement) {
        if ($linkableElement['conf'] > 0 && (!empty($object->linkedObjectsIds[$linkableElement['link_name']]))) {
            $className    = $linkableElement['className'];
            $linkedObject = new $className($db);

            $linkedObjectKey = array_key_first($object->linkedObjectsIds[$linkableElement['link_name']]);
            $linkedObjectId  = $object->linkedObjectsIds[$linkableElement['link_name']][$linkedObjectKey];

            $result = $linkedObject->fetch($linkedObjectId);

            if ($result > 0) {
                print '<tr><td class="titlefield">';
                print $langs->trans($linkableElement['langs']);
                print '</td>';
                print '<td>';

                print $linkedObject->getNomUrl(1);
                print property_exists($linkedObject, 'label') ? '<span class="opacitymedium">' . ' - ' . dol_trunc($linkedObject->label) . '</span>' : '';


                if ($linkedObject->array_options['options_qc_frequency'] > 0) {
                    print ' ';
                    print '<strong>';
                    print $langs->transnoentities('QcFrequency') . ' : ' . $linkedObject->array_options['options_qc_frequency'];
                    print '</strong>';
                }

                print '<td></tr>';
            }
        }
    }

    print '<tr class="linked-medias photo question-table"><td class=""><label for="photos">' . $langs->trans("Photo") . '</label></td><td class="linked-medias-list">';
    $pathPhotos = $conf->digiquali->multidir_output[$conf->entity] . '/control/' . $object->ref . '/photos/';
    $fileArray  = dol_dir_list($pathPhotos, 'files');
?>
    <span class="add-medias" <?php echo ($object->status < Control::STATUS_LOCKED) ? '' : 'style="display:none"' ?>>
        <input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-photo-default" type="file" name="userfile[]" capture="environment" accept="image/*">
        <input type="hidden" class="fast-upload-options" data-from-subtype="photo" data-from-subdir="photos" />
        <label for="fast-upload-photo-default">
            <div class="wpeo-button <?php echo ($onPhone ? 'button-square-40' : 'button-square-50'); ?>">
                <i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
            </div>
        </label>
        <input type="hidden" class="favorite-photo" id="photo" name="photo" value="<?php echo $object->photo ?>" />
        <div class="wpeo-button <?php echo ($onPhone ? 'button-square-40' : 'button-square-50'); ?> 'open-media-gallery add-media modal-open" value="0">
            <input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo $object->id ?>" data-from-type="control" data-from-subtype="photo" data-from-subdir="photos" />
            <i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
        </div>
    </span>
    <?php
    print saturne_show_medias_linked('digiquali', $pathPhotos, 'small', 0, 0, 0, 0, $onPhone ? 40 : 50, $onPhone ? 40 : 50, 0, 0, 0, 'control/' . $object->ref . '/photos/', $object, 'photo', $object->status < Control::STATUS_LOCKED, $permissiontodelete && $object->status < Control::STATUS_LOCKED);
    print '</td></tr>';

    $averagePercentageQuestions = 0;
    $percentQuestionCounter     = 0;
    if (is_array($sheet->linkedObjects['digiquali_question']) && !empty($sheet->linkedObjects['digiquali_question'])) {
        foreach ($sheet->linkedObjects['digiquali_question'] as $questionLinked) {
            if ($questionLinked->type !== 'Percentage') {
                continue; // Skip non-percentage questions
            }

            $percentQuestionCounter++;
            foreach ($object->lines as $line) {
                if ($line->fk_question === $questionLinked->id) {
                    $averagePercentageQuestions += $line->answer;
                }
            }
        }
    }

    $averagePercentageQuestions = ($percentQuestionCounter > 0) ? ($averagePercentageQuestions / $percentQuestionCounter) : 0;

    if ($percentQuestionCounter > 0) {
        print '<tr class="field_success_rate"><td class="titlefield fieldname_success_rate">';
        print $form->editfieldkey('SuccessScore', 'success_rate', $object->success_rate, $object, $permissiontoadd && $object->status < Control::STATUS_LOCKED, 'string', '', 0, 0, 'id', $langs->trans('PercentageValue'));
        print '</td><td class="valuefield fieldname_success_rate">';
        if ($action == 'editsuccess_rate') {
            print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="post">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="action" value="setsuccess_rate">';
            print '<table class="nobordernopadding centpercent">';
            print '<tbody><tr><td><input type="number" id="success_rate" name="success_rate" min="0" max="100" onkeyup=window.saturne.utils.enforceMinMax(this) value="' . $object->success_rate . '">';
            print '</td><td class="left"><input type="submit" class="smallpaddingimp button" name="modify" value="' . $langs->trans('Modify') . '"><input type="submit" class="smallpaddingimp button button-cancel" name="cancel" value="' . $langs->trans('Cancel') . '"></td></tr></tbody></table>';
            print '</form>';
        } else {
            print (!empty($object->success_rate) ? price2num($object->success_rate) : 0) . ' %';
        }
        print '</td></tr>';

        print '<tr class="field_average"><td class="titlefield fieldname_average">';
        print $langs->trans('AveragePercentageQuestions');
        print '</td><td class="valuefield fieldname_average">';
        print '<span class="badge badge-' . ($object->success_rate > $averagePercentageQuestions ? 'status8' : 'status4') . ' badge-status' . '">' . price2num($averagePercentageQuestions) . ' %</div>';
        print '</td></tr>';
    }

    // Other attributes. Fields from hook formObjectOptions and Extrafields
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

    print '</table>';
    print '</div>';
    print '</div>';

    $cantValidateControl = 0;
    $mandatoryArray      = json_decode($sheet->mandatory_questions, true);
    if (is_array($mandatoryArray) && !empty($mandatoryArray) && is_array($questionIds) && !empty($questionIds)) {
        foreach ($questionIds as $questionId) {
            if (in_array($questionId, $mandatoryArray)) {
                $controldettmp = $objectLine;
                $resultQuestion = $question->fetch($questionId);
                $resultAnswer = $controldettmp->fetchFromParentWithQuestion($object->id, $questionId);
                if (($resultAnswer > 0 && is_array($resultAnswer)) || !empty($controldettmp)) {
                    $itemControlDet = !empty($resultAnswer) ? array_shift($resultAnswer) : $controldettmp;
                    if ($resultQuestion > 0) {
                        if (empty($itemControlDet->comment) && empty($itemControlDet->answer)) {
                            $cantValidateControl++;
                        }
                    }
                }
            }
        }
    }

    print '<div class="clearboth"></div>';

    if ($equipmentOutdated == true) { ?>
        <div class="wpeo-notice notice-error">
            <div class="notice-content">
                <div class="notice-title"><?php echo $langs->trans('ControlEquipmentOutdated') ?></div>
            </div>
            <a class="butAction" href="<?php echo DOL_URL_ROOT . '/custom/digiquali/view/control/control_equipment.php?id=' . $object->id ?>"><?php echo $langs->trans("GoToEquipmentHours", $usertmp->getFullName($langs)) ?></a>
        </div>
    <?php }

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?action=save&id=' . $object->id . '" id="saveObject" enctype="multipart/form-data">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="save">';

    // Buttons for actions
    if ($action != 'presend') {
        print '<div class="tabsAction">';
        $parameters = [];
        $resHook    = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
        if ($resHook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        }

        if (empty($resHook)) {
            // Save question answer
            $displayButton = $onPhone ? '<i class="fas fa-save fa-2x"></i>' : '<i class="fas fa-save"></i>' . ' ' . $langs->trans('Save');
            if ($object->status == Control::STATUS_DRAFT) {
                print '<span class="butActionRefused" id="saveButton" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=save' . '">' . $displayButton . ' <i class="fas fa-circle" style="color: red; display: none; ' . ($onPhone ? 'vertical-align: top;' : '') . '"></i></span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Validate
            $displayButton = $onPhone ? '<i class="fas fa-check fa-2x"></i>' : '<i class="fas fa-check"></i>' . ' ' . $langs->trans('Validate');
            if ($object->status == $object::STATUS_DRAFT && empty($cantValidateControl) && !$equipmentOutdated) {
                print '<span class="validateButton butAction" id="actionButtonValidate">' . $displayButton . '</span>';
            } else if ($cantValidateControl > 0) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('QuestionMustBeAnswered', $cantValidateControl)) . '">' . $displayButton . '</span>';
            } else if ($equipmentOutdated) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ControlEquipmentOutdated'))  . '">' . $displayButton . '</span>';
            } elseif ($object->status < $object::STATUS_DRAFT) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ControlMustBeDraft')) . '">' . $displayButton . '</span>';
            }

            // ReOpen
            $displayButton = $onPhone ? '<i class="fas fa-lock-open fa-2x"></i>' : '<i class="fas fa-lock-open"></i>' . ' ' . $langs->trans('ReOpenDoli');
            if ($object->status == Control::STATUS_VALIDATED) {
                print '<span class="butAction" id="actionButtonInProgress">' . $displayButton . '</span>';
            } elseif ($object->status > Control::STATUS_VALIDATED) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidated', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Set verdict control
            $displayButton = $onPhone ? '<i class="far fa-check-circle fa-2x"></i>' : '<i class="far fa-check-circle"></i>' . ' ' . $langs->trans('SetOK/KO');
            if ($object->status == $object::STATUS_VALIDATED && $object->verdict == null && !$equipmentOutdated) {
                if ($permissiontosetverdict) {
                    print '<span class="butAction" id="actionButtonVerdict">' . $displayButton . '</span>';
                }
            } elseif ($object->status == $object::STATUS_DRAFT) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ControlMustBeValidatedToSetVerdict')) . '">' . $displayButton . '</span>';
            } else if ($equipmentOutdated) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ControlEquipmentOutdated'))  . '">' . $displayButton . '</span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ControlVerdictSelected'))  . '">' . $displayButton . '</span>';
            }

            // Sign
            $displayButton = $onPhone ? '<i class="fas fa-signature fa-2x"></i>' : '<i class="fas fa-signature"></i>' . ' ' . $langs->trans('Sign');
            if ($object->status == $object::STATUS_VALIDATED && !$signatory->checkSignatoriesSignatures($object->id, $object->element) && $object->verdict > 0) {
                print '<a class="butAction" id="actionButtonSign" href="' . dol_buildpath('/custom/saturne/view/saturne_attendants.php?id=' . $object->id . '&module_name=DigiQuali&object_type=' . $object->element . '&document_type=ControlDocument&attendant_table_mode=simple', 3) . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidatedToSign', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Lock
            $displayButton = $onPhone ? '<i class="fas fa-lock fa-2x"></i>' : '<i class="fas fa-lock"></i>' . ' ' . $langs->trans('Lock');
            if ($object->status == $object::STATUS_VALIDATED && $object->verdict != null && $signatory->checkSignatoriesSignatures($object->id, $object->element) && !$equipmentOutdated) {
                print '<span class="butAction" id="actionButtonLock">' . $displayButton . '</span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ControlMustBeValidatedToLock')) . '">' . $displayButton . '</span>';
            }

            // Send email
            $displayButton = $onPhone ? '<i class="fas fa-envelope fa-2x"></i>' : '<i class="fas fa-envelope"></i>' . ' ' . $langs->trans('SendMail') . ' ';
            if ($object->status == $object::STATUS_LOCKED) {
                $fileparams = dol_most_recent_file($upload_dir . '/' . $object->element . 'document' . '/' . $object->ref);
                $file       = $fileparams['fullname'];
                if (file_exists($file) && !strstr($fileparams['name'], 'specimen')) {
                    $forcebuilddoc = 0;
                } else {
                    $forcebuilddoc = 1;
                }
                print dolGetButtonAction($displayButton, '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=presend&forcebuilddoc=' . $forcebuilddoc . '&mode=init#formmailbeforetitle', '', $object->status == $object::STATUS_LOCKED);
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToSendEmail', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Archive
            $displayButton = $onPhone ?  '<i class="fas fa-archive fa-2x"></i>' : '<i class="fas fa-archive"></i>' . ' ' . $langs->trans('Archive');
            if ($object->status == Control::STATUS_LOCKED && !empty(dol_dir_list($upload_dir . '/' . $object->element . 'document/' . dol_sanitizeFileName($object->ref)))) {
                print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=confirm_archive&token=' . newToken() . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToArchive', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Clone
            $displayButton = $onPhone ? '<i class="fas fa-clone fa-2x"></i>' : '<i class="fas fa-clone"></i>' . ' ' . $langs->trans('ToClone');
            print '<span class="butAction" id="actionButtonClone">' . $displayButton . '</span>';

            // Delete (need delete permission, or if draft, just need create/modify permission)
            $displayButton = $onPhone ? '<i class="fas fa-trash fa-2x"></i>' : '<i class="fas fa-trash"></i>' . ' ' . $langs->trans('Delete');
            print dolGetButtonAction($displayButton, '', 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken(), '', $permissiontodelete || ($object->status == Control::STATUS_DRAFT && $permissiontoadd));
        }
        print '</div>';
    }

    // QUESTION LINES
    print '<div class="div-table-responsive-no-min questionLines" style="overflow-x: unset !important">';

    if (is_array($questionIds) && !empty($questionIds)) {
        ksort($questionIds);
    } ?>

    <div class="progress-info">
        <span class="badge badge-info" style="margin-right: 10px;"><?php print $answerCounter . '/' . $questionCounter; ?></span>
        <div class="progress-bar" style="margin-right: 10px;">
            <?php
            $percentage = ($answerCounter / $questionCounter) * 100;
            if ($percentage == 0) {
                print ('<div class="progress progress-bar-danger" style="width: 0%;" title="' . ($questionCounter > 0 ? $answerCounter . '/' . $questionCounter : 0) . '"></div>');
            }
            if ($percentage > 0 && $percentage < 100) {
                print ('<div class="progress progress-bar-warning" style="width:' . ($questionCounter > 0 ? ($answerCounter / $questionCounter) * 100 : 0) . '%;" title="' . ($questionCounter > 0 ? $answerCounter . '/' . $questionCounter : 0) . '"></div>');
            }
            if ($percentage == 100) {
                print ('<div class="progress progress-bar-success" style="width: 100%;" title="' . ($questionCounter > 0 ? $answerCounter . '/' . $questionCounter : 0) . '"></div>');
            }
            ?>
        </div>
        <?php if ($answerCounter != $questionCounter) {
            print $user->conf->DIGIQUALI_SHOW_ONLY_QUESTIONS_WITH_NO_ANSWER ? img_picto($langs->trans('Enabled'), 'switch_on', 'class="show-only-questions-with-no-answer marginrightonly"') : img_picto($langs->trans('Disabled'), 'switch_off', 'class="show-only-questions-with-no-answer marginrightonly"');
            print $form->textwithpicto($user->conf->DIGIQUALI_SHOW_ONLY_QUESTIONS_WITH_NO_ANSWER ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>', $langs->trans('ShowOnlyQuestionsWithNoAnswer'));
        } else {
            $user->conf->DIGIQUALI_SHOW_ONLY_QUESTIONS_WITH_NO_ANSWER = 0;
        } ?>
    </div>

<?php if (!$user->conf->DIGIQUALI_SHOW_ONLY_QUESTIONS_WITH_NO_ANSWER || $answerCounter != $questionCounter) {
        print load_fiche_titre($langs->trans('LinkedQuestionsList'), '', '');
        print '<div id="tablelines" class="question-answer-container noborder noshadow">';
        require_once __DIR__ . '/../../core/tpl/digiquali_answers.tpl.php';
        print '</div>';
    }

    print '</div>';
    print '</form>';
    print dol_get_fiche_end();

    if ($action != 'presend') {
        print '<div class="fichecenter"><div class="fichehalfleft">';

        $objRef    = dol_sanitizeFileName($object->ref);
        $dirFiles  = $object->element . 'document/' . $objRef;
        $fileDir   = $upload_dir . '/' . $dirFiles;
        $urlSource = $_SERVER['PHP_SELF'] . '?id=' . $object->id;

        print saturne_show_documents('digiquali:' . ucfirst($object->element) . 'Document', $dirFiles, $fileDir, $urlSource, $permissiontoadd, $permissiontodelete, $conf->global->DIGIQUALI_CONTROLDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, '', '', '', $langs->defaultlang, '', $object, 0, 'remove_file', (($object->status > CONTROL::STATUS_DRAFT) ? 1 : 0), $langs->trans('ObjectMustBeValidatedToGenerate', ucfirst($langs->transnoentities('The' . ucfirst($object->element)))));
        print '</div>';

        print '</div><div class="fichehalfright">';

        $moreHtmlCenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/saturne/view/saturne_agenda.php', 1) . '?id=' . $object->id . '&module_name=DigiQuali&object_type=' . $object->element);

        // List of actions on element
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
        $formActions = new FormActions($db);
        $formActions->showactions($object, $object->element . '@' . $object->module, 0, 1, '', 10, '', $moreHtmlCenter);

        print '</div></div>';
    }

    //Select mail models is same action as presend
    if (GETPOST('modelselected')) {
        $action = 'presend';
    }

    if ($action == 'presend') {
        $langs->load('mails');

        $ref = dol_sanitizeFileName($object->ref);
        $filelist = dol_dir_list($upload_dir . '/' . $object->element . 'document' . '/' . $ref, 'files', 0, '', '', 'date', SORT_DESC);
        if (!empty($filelist) && is_array($filelist)) {
            $filetype = ['controldocument' => 0];
            foreach ($filelist as $file) {
                if (!strstr($file['name'], 'specimen')) {
                    if (strstr($file['name'], str_replace(' ', '_', $langs->transnoentities('controldocument'))) && $filetype['controldocument'] == 0) {
                        $files[] = $file['fullname'];
                        $filetype['controldocument'] = 1;
                    }
                }
            }
        }

        // Define output language
        $outputlangs = $langs;
        $newlang     = '';
        if (!empty($conf->global->MAIN_MULTILANGS) && empty($newlang)) {
            $newlang = $object->thirdparty->default_lang;
            if (GETPOST('lang_id', 'aZ09')) {
                $newlang = GETPOST('lang_id', 'aZ09');
            }
        }

        if (!empty($newlang)) {
            $outputlangs = new Translate('', $conf);
            $outputlangs->setDefaultLang($newlang);
        }

        print '<div id="formmailbeforetitle" name="formmailbeforetitle"></div>';
        print '<div class="clearboth"></div>';
        print '<br>';
        print load_fiche_titre($langs->trans('SendMail'), '', $object->picto);

        print dol_get_fiche_head();

        // Create form for email.
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
        $formmail = new FormMail($db);

        $formmail->param['langsmodels'] = (empty($newlang) ? $langs->defaultlang : $newlang);
        $formmail->fromtype = (GETPOST('fromtype') ? GETPOST('fromtype') : (!empty($conf->global->MAIN_MAIL_DEFAULT_FROMTYPE) ? $conf->global->MAIN_MAIL_DEFAULT_FROMTYPE : 'user'));

        if ($formmail->fromtype === 'user') {
            $formmail->fromid = $user->id;
        }

        $formmail->withfrom = 1;

        // Define $liste, a list of recipients with email inside <>.
        $liste = [];
        if (!empty($object->socid) && $object->socid > 0 && !is_object($object->thirdparty) && method_exists($object, 'fetch_thirdparty')) {
            $object->fetch_thirdparty();
        }
        if (is_object($object->thirdparty)) {
            foreach ($object->thirdparty->thirdparty_and_contact_email_array(1) as $key => $value) {
                $liste[$key] = $value;
            }
        }

        if (!empty($conf->global->MAIN_MAIL_ENABLED_USER_DEST_SELECT)) {
            $listeuser = [];
            $fuserdest = new User($db);

            $result = $fuserdest->fetchAll('ASC', 't.lastname', 0, 0, ['customsql' => "t.statut = 1 AND t.employee = 1 AND t.email IS NOT NULL AND t.email <> ''"], 'AND', true);
            if ($result > 0 && is_array($fuserdest->users) && count($fuserdest->users) > 0) {
                foreach ($fuserdest->users as $uuserdest) {
                    $listeuser[$uuserdest->id] = $uuserdest->user_get_property($uuserdest->id, 'email');
                }
            } elseif ($result < 0) {
                setEventMessages(null, $fuserdest->errors, 'errors');
            }
            if (count($listeuser) > 0) {
                $formmail->withtouser = $listeuser;
                $formmail->withtoccuser = $listeuser;
            }
        }

        //$arrayoffamiliestoexclude=array('system', 'mycompany', 'object', 'objectamount', 'date', 'user', ...);
        if (!isset($arrayoffamiliestoexclude)) {
            $arrayoffamiliestoexclude = null;
        }

        // Make substitution in email content.
        if ($object) {
            // First we set ->substit (useless, it will be erased later) and ->substit_lines.
            $formmail->setSubstitFromObject($object, $langs);
        }
        $substitutionarray                = getCommonSubstitutionArray($outputlangs, 0, $arrayoffamiliestoexclude, $object);
        $substitutionarray['__TYPE__']    = $langs->trans(ucfirst($object->element));
        $substitutionarray['__THETYPE__'] = $langs->trans('The' . ucfirst($object->element));

        $parameters = ['mode' => 'formemail'];
        complete_substitutions_array($substitutionarray, $outputlangs, $object, $parameters);

        // Find all external contact addresses
        $tmpobject  = $object;
        $contactarr = [];
        $contactarr = $tmpobject->liste_contact(-1);

        if (is_array($contactarr) && count($contactarr) > 0) {
            require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
            $contactstatic = new Contact($db);
            $tmpcompany = new Societe($db);

            foreach ($contactarr as $contact) {
                $contactstatic->fetch($contact['id']);
                // Complete substitution array
                $substitutionarray['__CONTACT_NAME_' . $contact['code'] . '__']      = $contactstatic->getFullName($outputlangs, 1);
                $substitutionarray['__CONTACT_LASTNAME_' . $contact['code'] . '__']  = $contactstatic->lastname;
                $substitutionarray['__CONTACT_FIRSTNAME_' . $contact['code'] . '__'] = $contactstatic->firstname;
                $substitutionarray['__CONTACT_TITLE_' . $contact['code'] . '__']     = $contactstatic->getCivilityLabel();

                // Complete $liste with the $contact
                if (empty($liste[$contact['id']])) {    // If this contact id not already into the $liste.
                    $contacttoshow = '';
                    if (isset($object->thirdparty) && is_object($object->thirdparty)) {
                        if ($contactstatic->fk_soc != $object->thirdparty->id) {
                            $tmpcompany->fetch($contactstatic->fk_soc);
                            if ($tmpcompany->id > 0) {
                                $contacttoshow .= $tmpcompany->name . ': ';
                            }
                        }
                    }
                    $contacttoshow .= $contactstatic->getFullName($outputlangs, 1);
                    $contacttoshow .= ' <' . ($contactstatic->email ?: $langs->transnoentitiesnoconv('NoEMail')) . '>';
                    $liste[$contact['id']] = $contacttoshow;
                }
            }
        }

        $formmail->withto              = $liste;
        $formmail->withtofree          = (GETPOSTISSET('sendto') ? (GETPOST('sendto', 'alphawithlgt') ? GETPOST('sendto', 'alphawithlgt') : '1') : '1');
        $formmail->withtocc            = $liste;
        $formmail->withtoccc           = getDolGlobalString('MAIN_EMAIL_USECCC');
        $formmail->withtopic           = $outputlangs->trans('SendMailSubject', '__REF__');
        $formmail->withfile            = 2;
        $formmail->withbody            = 1;
        $formmail->withdeliveryreceipt = 1;
        $formmail->withcancel          = 1;

        // Array of substitutions.
        $formmail->substit = $substitutionarray;

        // Array of other parameters.
        $formmail->param['action']    = 'send';
        $formmail->param['models']    = 'saturne';
        $formmail->param['models_id'] = GETPOST('modelmailselected', 'int');
        $formmail->param['id']        = $object->id;
        $formmail->param['returnurl'] = $_SERVER['PHP_SELF'] . '?id=' . $object->id;
        $formmail->param['fileinit']  = $files;
        $formmail->trackid            = 'control' . $object->id;

        // Show form.
        print $formmail->get_form();

        print dol_get_fiche_end();
    }
}

// End of page
llxFooter();
$db->close();
