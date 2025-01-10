<?php
/* Copyright (C) 2024 EVARISK <technique@evarisk.com>
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
 * \file    view/survey/survey_card.php
 * \ingroup digiquali
 * \brief   Page to create/edit/view survey
 */

// Load DigiQuali environment
if (file_exists('../digiquali.main.inc.php')) {
    require_once __DIR__ . '/../digiquali.main.inc.php';
} elseif (file_exists('../../digiquali.main.inc.php')) {
    require_once __DIR__ . '/../../digiquali.main.inc.php';
} else {
    die('Include of digiquali main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

// Load Saturne libraries
require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

// Load DigiQuali libraries
require_once __DIR__ . '/../../class/survey.class.php';
require_once __DIR__ . '/../../class/sheet.class.php';
require_once __DIR__ . '/../../class/question.class.php';
require_once __DIR__ . '/../../class/answer.class.php';
require_once __DIR__ . '/../../class/digiqualidocuments/surveydocument.class.php';
require_once __DIR__ . '/../../lib/digiquali_sheet.lib.php';
require_once __DIR__ . '/../../lib/digiquali_survey.lib.php';
require_once __DIR__ . '/../../lib/digiquali_answer.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$subaction           = GETPOST('subaction', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'surveycard'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize objects
// Technical objets
$object      = new Survey($db);
$objectLine  = new SurveyLine($db);
$document    = new SurveyDocument($db);
$signatory   = new SaturneSignature($db, 'digiquali');
$sheet       = new Sheet($db);
$question    = new Question($db);
$answer      = new Answer($db);
$extraFields = new ExtraFields($db);
$category    = new Categorie($db);

// View objects
$form = new Form($db);

$hookmanager->initHooks(['surveycard', 'globalcard']); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extraFields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extraFields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$searchAll = GETPOST('search_all', 'alpha');
$search    = [];
foreach ($object->fields as $key => $val) {
    if (GETPOST('search_' . $key, 'alpha')) {
        $search[$key] = GETPOST('search_' . $key, 'alpha');
    }
}

if (empty($action) && empty($id) && empty($ref)) {
    $action = 'view';
}

// Load object
require_once DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be included, not include_once

$upload_dir = $conf->digiquali->multidir_output[$object->entity ?? 1];

// Security check - Protection if external user
$permissionToRead   = $user->rights->digiquali->survey->read;
$permissiontoadd    = $user->rights->digiquali->survey->write;
$permissiontodelete = $user->rights->digiquali->survey->delete || ($permissiontoadd && isset($object->status) && $object->status == Survey::STATUS_DRAFT);
saturne_check_access($permissionToRead);

/*
 * Actions
 */

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    $error = 0;

    $backurlforlist = dol_buildpath('/digiquali/view/survey/survey_list.php', 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/digiquali/view/survey/survey_card.php', 1) . '?id=' . ((!empty($id) && $id > 0) ? $id : '__ID__');
            }
        }
    }

    // Action clone object
    if ($action == 'confirm_clone' && $confirm == 'yes') {
        $options['attendants'] = GETPOST('clone_attendants');
        $options['photos']     = GETPOST('clone_photos');
        if ($object->id > 0) {
            $result = $object->createFromClone($user, $object->id, $options);
            if ($result > 0) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $result);
                exit();
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
            }
        }
    }

    if ($action == 'add' && !$cancel) {
        $linkableElements = get_sheet_linkable_objects();
        $linkedObjectSelected = 0;

        if (!empty($linkableElements)) {
            foreach ($linkableElements as $linkableElementType => $linkableElement) {
                if (!empty(GETPOST($linkableElement['post_name'])) && GETPOST($linkableElement['post_name']) > 0) {
                    $linkedObjectSelected++;
                }
            }
        }

        if (GETPOST('fk_sheet') > 0) {
            if ($linkedObjectSelected == 0) {
                setEventMessages($langs->trans('NeedObjectToLink'), [], 'errors');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?action=create&fk_sheet=' . GETPOST('fk_sheet'));
                exit;
            }
        } else {
            setEventMessages($langs->trans('NeedFkSheet'), [], 'errors');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?action=create');
            exit;
        }
    }

    // Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
    require_once DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';

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

    // Actions builddoc, forcebuilddoc, remove_file
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/documents_action.tpl.php';

    // Action to generate pdf from odt file
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/saturne_manual_pdf_generation_action.tpl.php';

    // Actions confirm_lock, confirm_archive
    require_once __DIR__ . '/../../../saturne/core/tpl/actions/object_workflow_actions.tpl.php';

    // Actions to send emails
    $triggersendname = 'SURVEY_SENTBYMAIL';
    $autocopy        = 'MAIN_MAIL_AUTOCOPY_AUDIT_TO';
    $trackid         = 'survey' . $object->id;
    require_once DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
}

/*
 * View
 */

$title   = $langs->trans(ucfirst($object->element));
$helpUrl = 'FR:Module_DigiQuali';
$moreJS  = ['/saturne/js/includes/hammer.min.js'];

saturne_header(1,'', $title, $helpUrl, '', 0, 0, $moreJS);

$elementArray = get_sheet_linkable_objects();

// Part to create
if ($action == 'create') {
    if (empty($permissiontoadd)) {
        accessforbidden($langs->trans('NotEnoughPermissions'), 0);
        exit;
    }

    print load_fiche_titre($langs->trans('New' . ucfirst($object->element)), '', 'object_' . $object->picto);

    print '<form method="POST" id="createObjectForm" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="add">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
    }
    if ($backtopageforcancel) {
        print '<input type="hidden" name="backtopageforcancel" value="'. $backtopageforcancel . '">';
    }

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldcreate">';

    if (!empty(GETPOST('fk_sheet'))) {
        $sheet->fetch(GETPOST('fk_sheet'));
    }

    //FK SHEET
    print '<tr><td class="fieldrequired">' . $langs->trans('Sheet') . '</td><td>';
    print img_picto('', $sheet->picto, 'class="pictofixedwidth"') . $sheet->selectSheetList(GETPOST('fk_sheet') ?: $sheet->id, 'fk_sheet', 's.type = ' . '"' . $object->element . '" AND s.status = ' . Sheet::STATUS_LOCKED);
    print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/custom/digiquali/view/sheet/sheet_card.php?action=create" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddSheet') . '"></span></a>';
    print '</td></tr>';

    // Common attributes
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

    // Categories
    if (isModEnabled('categorie')) {
        print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
        $categoriesArborescence = $form->select_all_categories($object->element, '', 'parent', 64, 0, 1);
        print img_picto('', 'category', 'class="pictofixedwidth"').$form::multiselectarray('categories', $categoriesArborescence, GETPOST('categories', 'array'), '', 0, 'minwidth100imp maxwidth500 widthcentpercentminusxx');
        print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/categories/index.php?type=' . $object->element . '&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?action=create') . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddCategories') . '"></span></a>';
        print '</td></tr>';
    }

    print '</table>';
    print '<hr>';

    print '<table class="border centpercent tableforfieldcreate object-table linked-objects">';

    print '<div class="fields-content">';

    foreach($elementArray as $linkableElementType => $linkableElement) {
        if (!empty($linkableElement['conf'] && preg_match('/"' . $linkableElementType . '":1/', $sheet->element_linked))) {

            $objectArray    = [];
            $objectPostName = $linkableElement['post_name'];
            $objectPost     = GETPOST($objectPostName) ?: (GETPOST('fromtype') == $linkableElement['link_name'] ? GETPOST('fromid') : '');

            if ((dol_strlen($linkableElement['fk_parent']) > 0 && GETPOST($linkableElement['parent_post']) > 0)) {
                $objectFilter = [
                    'customsql' => $linkableElement['fk_parent'] . ' = ' . GETPOST($linkableElement['parent_post'])
                ];
            } else {
                $objectFilter = [];
            }

            $objectList = saturne_fetch_all_object_type($linkableElement['className'], '', '', 0, 0, $objectFilter);
            if (is_array($objectList) && !empty($objectList)) {
                foreach($objectList as $objectSingle) {
                    $objectName = '';
                    $nameField = $linkableElement['name_field'];
                    if (strstr($nameField, ',')) {
                        $nameFields = explode(', ', $nameField);
                        if (is_array($nameFields) && !empty($nameFields)) {
                            foreach($nameFields as $subnameField) {
                                $objectName .= $objectSingle->$subnameField . ' ';
                            }
                        }
                    } else {
                        $objectName = $objectSingle->$nameField;
                    }
                    $objectArray[$objectSingle->id] = $objectName;
                }
            }

            print '<tr><td class="titlefieldcreate">' . $langs->transnoentities($linkableElement['langs']) . '</td><td>';
            print img_picto('', $linkableElement['picto'], 'class="pictofixedwidth"');
            print $form::selectarray($objectPostName, $objectArray, $objectPost, $langs->trans('Select') . ' ' . strtolower($langs->trans($linkableElement['langs'])), 0, 0, '', 0, 0, dol_strlen(GETPOST('fromtype')) > 0 && GETPOST('fromtype') != $linkableElement['link_name'], '', 'maxwidth500 widthcentpercentminusxx');
            print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/' . $linkableElement['create_url'] . '?action=create&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?action=create') . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('Create') . ' ' . strtolower($langs->trans($linkableElement['langs'])) . '"></span></a>';
            print '</td></tr>';
        }
    }

    print '</div>';

    // Other attributes
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

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
        foreach($object->lines as $objectLine) {
            if (dol_strlen($objectLine->answer) > 0) {
                $answerCounter++;
            }
        }
    }

    $formConfirm = '';

    // Validate confirmation
    if (($action == 'validate' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $questionConfirmInfo = $langs->trans('YouAnswered') . ' ' . $answerCounter . ' ' . $langs->trans('question(s)')  . ' ' . $langs->trans('On') . ' ' . $questionCounter . '.';
        if ($questionCounter - $answerCounter != 0) {
            $questionConfirmInfo .= '<br><b>' . $langs->trans('BewareQuestionsAnswered', $questionCounter - $answerCounter) . '</b>';
        }

        $questionConfirmInfo .= '<br><br><b>' . $langs->trans('ConfirmValidateSurvey') . '</b>';
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ValidateObject', $langs->transnoentities('The' . ucfirst($object->element))), $questionConfirmInfo, 'confirm_validate', '', 'yes', 'actionButtonValidate', 250);
    }

    // Draft confirmation
    if (($action == 'draft' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&object_type=' . $object->element, $langs->trans('ReOpenObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmReOpenObject', $langs->transnoentities('The' . ucfirst($object->element)), $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_setdraft', '', 'yes', 'actionButtonInProgress', 350, 600);
    }

    // Lock confirmation
    if (($action == 'lock' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('LockObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmLockObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_lock', '', 'yes', 'actionButtonLock', 350, 600);
    }

    // Archive confirmation
    if (($action == 'set_archive' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&forcebuilddoc=true', $langs->trans('ArchiveObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmArchiveObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_archive', '', 'yes', 'actionButtonArchive', 350, 600);
    }

    // Clone confirmation
    if (($action == 'clone' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        // Define confirmation messages
        $formQuestionClone = [
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
        $publicInterfaceUrl = dol_buildpath('custom/digiquali/public/survey/public_survey.php?track_id=' . $object->track_id . '&entity=' . $conf->entity, 3);
        print '<tr><td class="titlefield">' . $langs->trans('PublicInterface') . ' <a href="' . $publicInterfaceUrl . '" target="_blank"><i class="fas fa-qrcode"></i></a>';
        print showValueWithClipboardCPButton($publicInterfaceUrl, 0, '&nbsp;');
        print '</td>';
        print '<td>' . saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/survey/' . $object->ref . '/qrcode/', 'small', 1, 0, 0, 0, 80, 80, 0, 0, 0, 'survey/'. $object->ref . '/qrcode/', $object, '', 0, 0) . '</td></tr>';

        // Answer public interface
        $publicAnswerUrl = dol_buildpath('custom/digiquali/public/public_answer.php?track_id=' . $object->track_id . '&object_type=' . $object->element . '&document_type=SurveyDocument&entity=' . $conf->entity, 3);
        print '<tr><td class="titlefield">' . $langs->trans('PublicAnswer') . ' <a href="' . $publicAnswerUrl . '" target="_blank"><i class="fas fa-qrcode"></i></a>';
        print showValueWithClipboardCPButton($publicAnswerUrl, 0, '&nbsp;');
        print '</td><td>';
        print '<a href="' . $publicAnswerUrl . '" target="_blank">' . $langs->trans('GoToPublicAnswerPage') . ' <i class="fa fa-external-link"></a>';
        print '</td></tr>';
    }

    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

    // Categories
    if (isModEnabled('categorie')) {
        print '<tr><td class="valignmiddle">' . $langs->trans('Categories') . '</td>';
        if ($action != 'categories') {
            print '<td style="display: flex;">' . ($object->status < Survey::STATUS_LOCKED ? '<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=categories&id=' . $object->id . '">' . img_edit($langs->trans('Modify')) . '</a>' : '<img src="" alt="">');
            print $form->showCategories($object->id, 'survey', 1) . '</td>';
        }
        if ($permissiontoadd && $action == 'categories') {
            $categoriesArborescence = $form->select_all_categories('survey', '', 'parent', 64, 0, 1);
            if (is_array($categoriesArborescence) && !empty($categoriesArborescence)) {
                print '<td>';
                print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="post">';
                print '<input type="hidden" name="token" value="'.newToken().'">';
                print '<input type="hidden" name="action" value="set_categories">';

                $cats          = $category->containing($object->id, 'survey');
                $arraySelected = [];
                if (is_array($cats)) {
                    foreach ($cats as $cat) {
                        $arraySelected[] = $cat->id;
                    }
                }
                print img_picto('', 'category') . $form::multiselectarray('categories', $categoriesArborescence, (GETPOSTISSET('categories') ? GETPOST('categories', 'array') : $arraySelected), '', 0, 'minwidth100imp quatrevingtpercent widthcentpercentminusxx');
                print '<input type="submit" class="button button-edit small" value="'.$langs->trans('Save').'">';
                print '</form>';
                print '</td>';
            }
        }
        print '</tr>';
    }

    $object->fetchObjectLinked('', '', $object->id, 'digiquali_survey', 'OR', 1, 'sourcetype', 0);

    foreach($elementArray as $linkableElementType => $linkableElement) {
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
                print '<td></tr>';
            }
        }
    }

    print '<tr class="linked-medias photo question-table"><td class=""><label for="photos">' . $langs->trans('Photo') . '</label></td><td class="linked-medias-list">';
    $pathPhotos = $conf->digiquali->multidir_output[$conf->entity] . '/survey/'. $object->ref . '/photos/';
    $fileArray  = dol_dir_list($pathPhotos, 'files');
    ?>
    <span class="add-medias" <?php echo ($object->status < Survey::STATUS_LOCKED) ? '' : 'style="display:none"' ?>>
        <input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-photo-default" type="file" name="userfile[]" capture="environment" accept="image/*">
        <input type="hidden" class="fast-upload-options" data-from-subtype="photo" data-from-subdir="photos"/>
        <label for="fast-upload-photo-default">
            <div class="wpeo-button <?php echo ($onPhone ? 'button-square-40' : 'button-square-50'); ?>">
                <i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
            </div>
        </label>
        <input type="hidden" class="favorite-photo" id="photo" name="photo" value="<?php echo $object->photo ?>"/>
        <div class="wpeo-button <?php echo ($onPhone ? 'button-square-40' : 'button-square-50'); ?> 'open-media-gallery add-media modal-open" value="0">
            <input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo $object->id?>" data-from-type="survey" data-from-subtype="photo" data-from-subdir="photos"/>
            <i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
        </div>
    </span>
    <?php
    print saturne_show_medias_linked('digiquali', $pathPhotos, 'small', 0, 0, 0, 0, $onPhone ? 40 : 50, $onPhone ? 40 : 50, 0, 0, 0, 'survey/' . $object->ref . '/photos/', $object, 'photo', $object->status < Survey::STATUS_LOCKED, $permissiontodelete && $object->status < Survey::STATUS_LOCKED);
    print '</td></tr>';

    $averagePercentageQuestions = 0;
    $percentQuestionCounter     = 0;
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

    $averagePercentageQuestions = ($percentQuestionCounter > 0) ? ($averagePercentageQuestions / $percentQuestionCounter) : 0;

    if ($percentQuestionCounter > 0) {
        print '<tr class="field_success_rate"><td class="titlefield fieldname_success_rate">';
        print $form->editfieldkey('SuccessScore', 'success_rate', $object->success_rate, $object, $permissiontoadd && $object->status < Survey::STATUS_LOCKED, 'string', '', 0, 0,'id', $langs->trans('PercentageValue'));
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

    // @TODO pas opti
    $cantValidateSurvey = 0;
    $mandatoryArray     = json_decode($sheet->mandatory_questions, true);
    if (is_array($mandatoryArray) && !empty($mandatoryArray) && is_array($questionIds) && !empty($questionIds)) {
        foreach ($questionIds as $questionId) {
            if (in_array($questionId, $mandatoryArray)) {
                $resultQuestion = $question->fetch($questionId);
                $resultAnswer   = $objectLine->fetchFromParentWithQuestion($object->id, $questionId);
                if (($resultAnswer > 0 && is_array($resultAnswer)) || !empty($objectLine)) {
                    $itemSurveyDet = !empty($resultAnswer) ? array_shift($resultAnswer) : $objectLine;
                    if ($resultQuestion > 0) {
                        if (empty($itemSurveyDet->comment) && empty($itemSurveyDet->answer)) {
                            $cantValidateSurvey++;
                        }
                    }
                }
            }
        }
    }

    print '<div class="clearboth"></div>';

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?action=save&id=' . $object->id . '" id="saveObject" enctype="multipart/form-data">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
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
            if ($object->status == Survey::STATUS_DRAFT) {
                print '<span class="butActionRefused" id="saveButton" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=save' . '">' . $displayButton . ' <i class="fas fa-circle" style="color: red; display: none; ' . ($onPhone ? 'vertical-align: top;' : '') . '"></i></span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Validate
            $displayButton = $onPhone ? '<i class="fas fa-check fa-2x"></i>' : '<i class="fas fa-check"></i>' . ' ' . $langs->trans('Validate');
            if ($object->status == Survey::STATUS_DRAFT && empty($cantValidateSurvey)) {
                print '<span class="validateButton butAction" id="actionButtonValidate">' . $displayButton . '</span>';
            } elseif ($cantValidateSurvey > 0) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('QuestionMustBeAnswered', $cantValidateSurvey)) . '">' . $displayButton . '</span>';
            } elseif ($object->status < Survey::STATUS_DRAFT) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // ReOpen
            $displayButton = $onPhone ? '<i class="fas fa-lock-open fa-2x"></i>' : '<i class="fas fa-lock-open"></i>' . ' ' . $langs->trans('ReOpenDoli');
            if ($object->status == Survey::STATUS_VALIDATED) {
                print '<span class="butAction" id="actionButtonInProgress">' . $displayButton . '</span>';
            } elseif ($object->status > Survey::STATUS_VALIDATED) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidated', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Sign
            $displayButton = $onPhone ? '<i class="fas fa-signature fa-2x"></i>' : '<i class="fas fa-signature"></i>' . ' ' . $langs->trans('Sign');
            if ($object->status == Survey::STATUS_VALIDATED && !$signatory->checkSignatoriesSignatures($object->id, $object->element)) {
                print '<a class="butAction" id="actionButtonSign" href="' . dol_buildpath('/custom/saturne/view/saturne_attendants.php?id=' . $object->id . '&module_name=DigiQuali&object_type=' . $object->element . '&document_type=SurveyDocument&attendant_table_mode=simple', 3) . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidatedToSign', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Lock
            $displayButton = $onPhone ? '<i class="fas fa-lock fa-2x"></i>' : '<i class="fas fa-lock"></i>' . ' ' . $langs->trans('Lock');
            if ($object->status == Survey::STATUS_VALIDATED && $signatory->checkSignatoriesSignatures($object->id, $object->element)) {
                print '<span class="butAction" id="actionButtonLock">' . $displayButton . '</span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('AllSignatoriesMustHaveSigned', $langs->transnoentities('The' . ucfirst($object->element)))) . '">' . $displayButton . '</span>';
            }

            // Send email
            $displayButton = $onPhone ? '<i class="fas fa-envelope fa-2x"></i>' : '<i class="fas fa-envelope"></i>' . ' ' . $langs->trans('SendMail') . ' ';
            if ($object->status == Survey::STATUS_LOCKED) {
                $fileParams = dol_most_recent_file($upload_dir . '/' . $object->element . 'document' . '/' . $object->ref);
                $file       = $fileParams['fullname'];
                if (file_exists($file) && !strstr($fileParams['name'], 'specimen')) {
                    $forceBuildDoc = 0;
                } else {
                    $forceBuildDoc = 1;
                }
                print dolGetButtonAction($displayButton, '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=presend&forcebuilddoc=' . $forceBuildDoc . '&mode=init#formmailbeforetitle', '', $object->status == Survey::STATUS_LOCKED);
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToSendEmail', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Archive
            $displayButton = $onPhone ?  '<i class="fas fa-archive fa-2x"></i>' : '<i class="fas fa-archive"></i>' . ' ' . $langs->trans('Archive');
            if ($object->status == Survey::STATUS_LOCKED) {
                print '<span class="butAction" id="actionButtonArchive" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=confirm_archive&forcebuilddoc=true&token=' . newToken() . '">' . $displayButton . '</span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToArchive', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Clone
            $displayButton = $onPhone ? '<i class="fas fa-clone fa-2x"></i>' : '<i class="fas fa-clone"></i>' . ' ' . $langs->trans('ToClone');
            print '<span class="butAction" id="actionButtonClone">' . $displayButton . '</span>';

            // Delete (need delete permission, or if draft, just need create/modify permission)
            $displayButton = $onPhone ? '<i class="fas fa-trash fa-2x"></i>' : '<i class="fas fa-trash"></i>' . ' ' . $langs->trans('Delete');
            print dolGetButtonAction($displayButton, '', 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken(), '', $permissiontodelete || ($object->status == Survey::STATUS_DRAFT && $permissiontoadd));
        }
        print '</div>';
    }

    // QUESTION LINES
    print '<div class="div-table-responsive-no-min questionLines" style="overflow-x: unset !important;">';

    if (is_array($questionIds) && !empty($questionIds)) {
        ksort($questionIds);
    } ?>

    <div class="progress-info">
        <span class="badge badge-info" style="margin-right: 10px;"><?php print $answerCounter . '/' . $questionCounter; ?></span>
        <div class="progress-bar" style="margin-right: 10px;">
            <div class="progress progress-bar-success" style="width:<?php print ($questionCounter > 0 ? ($answerCounter/$questionCounter) * 100 : 0) . '%'; ?>;" title="<?php print ($questionCounter > 0 ? $answerCounter . '/' . $questionCounter : 0); ?>"></div>
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
        print '<div id="tablelines" class="question-answer-container">';
        require_once __DIR__ . '/../../core/tpl/digiquali_answers.tpl.php';
        print '</div>';
    }

    print '</div>';
    print '</form>';
    print dol_get_fiche_end();

    if ($action != 'presend') {
        print '<div class="fichecenter"><div class="fichehalfleft">';
        // Documents
        $objRef    = dol_sanitizeFileName($object->ref);
        $dirFiles  = $object->element . 'document/' . $objRef;
        $fileDir   = $upload_dir . '/' . $dirFiles;
        $urlSource = $_SERVER['PHP_SELF'] . '?id=' . $object->id;

        print saturne_show_documents('digiquali:' . ucfirst($object->element) . 'Document', $dirFiles, $fileDir, $urlSource, $permissiontoadd, $permissiontodelete, $conf->global->DIGIQUALI_SURVEYDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, '', '', '', $langs->defaultlang, '', $object, 0, 'remove_file', (($object->status > Survey::STATUS_DRAFT && $object->status != Survey::STATUS_ARCHIVED) ? 1 : 0), $langs->trans('ObjectMustBeValidatedToGenerate', ucfirst($langs->transnoentities('The' . ucfirst($object->element)))));

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

    //@todo a check
    // Presend form
    $modelmail    = $object->element;
    $defaulttopic = 'InformationMessage';
    $diroutput    = $conf->digiquali->dir_output;
    $trackid      = $object->element . $object->id;

    require_once DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
