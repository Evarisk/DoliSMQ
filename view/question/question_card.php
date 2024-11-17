<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
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
 *   	\file       view/question/question_card.php
 *		\ingroup    digiquali
 *		\brief      Page to create/edit/view question
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';

require_once '../../class/question.class.php';
require_once '../../class/answer.class.php';
require_once '../../lib/digiquali_question.lib.php';
require_once '../../lib/digiquali_answer.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user, $langs;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$subaction           = GETPOST('subaction', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'questioncard'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize objects
// Technical objets
$object         = new Question($db);
$answer         = new Answer($db);
$extrafields    = new ExtraFields($db);

// View objects
$form = new Form($db);

$hookmanager->initHooks(array('questioncard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$searchAll = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) $search[$key] = GETPOST('search_'.$key, 'alpha');
}

if (empty($action) && empty($id) && empty($ref)) $action = 'view';

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

$permissiontoread   = $user->rights->digiquali->question->read;
$permissiontoadd    = $user->rights->digiquali->question->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->digiquali->question->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);

// Security check - Protection if external user
saturne_check_access($permissiontoread, $object);

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	$error = 0;

	$backurlforlist = dol_buildpath('/digiquali/view/question/question_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) $backtopage = $backurlforlist;
			else $backtopage = dol_buildpath('/digiquali/view/question/question_card.php', 1).'?id='.($id > 0 ? $id : '__ID__');
		}
	}

	if ($cancel && $action != 'update') {
		$backtopage .= '#answerList';
	}

	if ($action == 'add' && !empty($permissiontoadd)) {
		foreach ($object->fields as $key => $val) {
			if ($object->fields[$key]['type'] == 'duration') {
				if (GETPOST($key.'hour') == '' && GETPOST($key.'min') == '') {
					continue; // The field was not submited to be edited
				}
			} else {
				if (!GETPOSTISSET($key)) {
					continue; // The field was not submited to be edited
				}
			}
			// Ignore special fields
			if (in_array($key, array('rowid', 'entity', 'import_key'))) {
				continue;
			}
			if (in_array($key, array('date_creation', 'tms', 'fk_user_creat', 'fk_user_modif'))) {
				if (!in_array(abs($val['visible']), array(1, 3))) {
					continue; // Only 1 and 3 that are case to create
				}
			}

			// Set value to insert
			if (in_array($object->fields[$key]['type'], array('text', 'html'))) {
				$value = GETPOST($key, 'restricthtml');
			} elseif ($object->fields[$key]['type'] == 'date') {
				$value = dol_mktime(12, 0, 0, GETPOST($key.'month', 'int'), GETPOST($key.'day', 'int'), GETPOST($key.'year', 'int'));	// for date without hour, we use gmt
			} elseif ($object->fields[$key]['type'] == 'datetime') {
				$value = dol_mktime(GETPOST($key.'hour', 'int'), GETPOST($key.'min', 'int'), GETPOST($key.'sec', 'int'), GETPOST($key.'month', 'int'), GETPOST($key.'day', 'int'), GETPOST($key.'year', 'int'), 'tzuserrel');
			} elseif ($object->fields[$key]['type'] == 'duration') {
				$value = 60 * 60 * GETPOST($key.'hour', 'int') + 60 * GETPOST($key.'min', 'int');
			} elseif (preg_match('/^(integer|price|real|double)/', $object->fields[$key]['type'])) {
				$value = price2num(GETPOST($key, 'alphanohtml')); // To fix decimal separator according to lang setup
			} elseif ($object->fields[$key]['type'] == 'boolean') {
				$value = ((GETPOST($key) == '1' || GETPOST($key) == 'on') ? 1 : 0);
			} elseif ($object->fields[$key]['type'] == 'reference') {
				$tmparraykey = array_keys($object->param_list);
				$value = $tmparraykey[GETPOST($key)].','.GETPOST($key.'2');
			} else {
				$value = GETPOST($key, 'alphanohtml');
			}
			if (preg_match('/^integer:/i', $object->fields[$key]['type']) && $value == '-1') {
				$value = ''; // This is an implicit foreign key field
			}
			if (!empty($object->fields[$key]['foreignkey']) && $value == '-1') {
				$value = ''; // This is an explicit foreign key field
			}

			//var_dump($key.' '.$value.' '.$object->fields[$key]['type']);
			$object->$key = $value;
			if ($val['notnull'] > 0 && $object->$key == '' && !is_null($val['default']) && $val['default'] == '(PROV)') {
				$object->$key = '(PROV)';
			}
			if ($val['notnull'] > 0 && $object->$key == '' && is_null($val['default'])) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv($val['label'])), null, 'errors');
			}
		}

		// Fill array 'array_options' with data from add form
		if (!$error) {
			$ret = $extrafields->setOptionalsFromPost(null, $object);
			if ($ret < 0) {
				$error++;
			}
		}

		if ( ! $error && ! empty($conf->global->MAIN_UPLOAD_DOC)) {
			// Define relativepath and upload_dir
			$relativepath                                             = '/question/tmp/QU0/photo_ok';
			$upload_dir                                               = $conf->digiquali->multidir_output[$conf->entity] . '/' . $relativepath;
			if (is_array($_FILES['userfile']['tmp_name'])) $userfiles = $_FILES['userfile']['tmp_name'];
			else $userfiles                                           = array($_FILES['userfile']['tmp_name']);

			foreach ($userfiles as $key => $userfile) {
				if (empty($_FILES['userfile']['tmp_name'][$key])) {
					if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
						setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
					}
					if ($_FILES['userfile']['error'][$key] == 4) {
						$error++;
					}
				}
			}

			if ( ! $error) {
				dol_add_file_process($upload_dir, 0, 1, 'userfile', '', null, '', 0);
				$imgThumbMini   = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_MINI, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_MINI, '_mini');
				$imgThumbSmall  = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_SMALL, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_SMALL, '_small');
				$imgThumbMedium = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
				$imgThumbLarge  = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_LARGE, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_LARGE, '_large');
			}
			$error = 0;
		}

		if ( ! $error && ! empty($conf->global->MAIN_UPLOAD_DOC)) {
			// Define relativepath and upload_dir
			$relativepath                                             = '/question/tmp/QU0/photo_ko';
			$upload_dir                                               = $conf->digiquali->multidir_output[$conf->entity] . '/' . $relativepath;
			if (is_array($_FILES['userfile2']['tmp_name'])) $userfiles = $_FILES['userfile2']['tmp_name'];
			else $userfiles                                           = array($_FILES['userfile2']['tmp_name']);

			foreach ($userfiles as $key => $userfile) {
				if (empty($_FILES['userfile2']['tmp_name'][$key])) {
					if ($_FILES['userfile2']['error'][$key] == 1 || $_FILES['userfile2']['error'][$key] == 2) {
						setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
					}
					if ($_FILES['userfile']['error'][$key] == 4) {
						$error++;
					}
				}
			}

			if ( ! $error) {
				dol_add_file_process($upload_dir, 0, 1, 'userfile2', '', null, '', 0);
				$imgThumbMini   = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_MINI, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_MINI, '_mini');
				$imgThumbSmall  = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_SMALL, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_SMALL, '_small');
				$imgThumbMedium = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
				$imgThumbLarge  = vignette($upload_dir, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_LARGE, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_LARGE, '_large');
			}
			$error = 0;
		}

		if (!$error) {
			$types = array('photo_ok', 'photo_ko');

			foreach ($types as $type) {
				$pathToTmpPhoto = $conf->digiquali->multidir_output[$conf->entity] . '/question/tmp/QU0/' . $type;
				$photoList = dol_dir_list($conf->digiquali->multidir_output[$conf->entity] . '/question/tmp/' . 'QU0/' . $type, 'files');
				if (is_array($photoList) && !empty($photoList)) {
					foreach ($photoList as $photo) {
						$pathToQuestionPhoto = $conf->digiquali->multidir_output[$conf->entity] . '/question/' . $object->getNextNumRef();
						if (!is_dir($pathToQuestionPhoto)) {
							mkdir($pathToQuestionPhoto);
						}
						$pathToQuestionPhotoType = $conf->digiquali->multidir_output[$conf->entity] . '/question/' . $object->getNextNumRef() . '/' . $type;
						if (!is_dir($pathToQuestionPhotoType)) {
							mkdir($pathToQuestionPhotoType);
						}

						copy($photo['fullname'], $pathToQuestionPhotoType . '/' . $photo['name']);

						$destfull = $pathToQuestionPhotoType . '/' . $photo['name'];

						if (empty($object->$type)) {
							$object->$type = $photo['name'];
						}

						$imgThumbMini   = vignette($destfull, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_MINI, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_MINI, '_mini');
						$imgThumbSmall  = vignette($destfull, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_SMALL, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_SMALL, '_small');
						$imgThumbMedium = vignette($destfull, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
						$imgThumbLarge  = vignette($destfull, $conf->global->DIGIQUALI_MEDIA_MAX_WIDTH_LARGE, $conf->global->DIGIQUALI_MEDIA_MAX_HEIGHT_LARGE, '_large');
						unlink($photo['fullname']);
					}
				}
				$filesThumbs = dol_dir_list($pathToTmpPhoto . '/thumbs/');
				if (is_array($filesThumbs) && !empty($filesThumbs)) {
					foreach ($filesThumbs as $fileThumb) {
						unlink($fileThumb['fullname']);
					}
				}
			}

			$result = $object->create($user);
			if ($result > 0) {
				// Creation OK
				// Category association
				$categories = GETPOST('categories', 'array');
				$object->setCategories($categories);
				
				//Mon super test
				$defaultAnswers = saturne_get_value_dictionary('c_question_type', 'default_answers', $object->type);
				
				if($defaultAnswers>0 && !empty($defaultAnswers->default_answers)){
				    $arrayDefaultAnswers = explode(',',$defaultAnswers->default_answers);
				    foreach($arrayDefaultAnswers as $answerp){
					$answerParameters = explode(':', $answerp);
					if(sizeof($answerParameters)==3){ //only if there are all parameters
					    $answer->fk_question = $result;
					    $answer->value       = $langs->transnoentities($answerParameters[0]);
					    $answer->pictogram   = $answerParameters[1];
					    $answer->color       = $answerParameters[2];
					    $answer->create($user);
					}
				    }  
				    
				}else{
				    //backward compatibility
				    if ($object->type == 'OkKo' || $object->type == 'OkKoToFixNonApplicable') {
					    $answer->fk_question = $result;
					    $answer->value       = $langs->transnoentities('OK');
					    $answer->pictogram   = 'check';
					    $answer->color       = '#47e58e';

					    $answer->create($user);

					    $answer->fk_question = $result;
					    $answer->value       = $langs->transnoentities('KO');
					    $answer->pictogram   = 'times';
					    $answer->color       = '#e05353';

					    $answer->create($user);
				    }

				    if ($object->type == 'OkKoToFixNonApplicable') {
					    $answer->fk_question = $result;
					    $answer->value       = $langs->transnoentities('ToFix');
					    $answer->pictogram   = 'tools';
					    $answer->color       = '#e9ad4f';

					    $answer->create($user);

					    $answer->fk_question = $result;
					    $answer->value       = $langs->transnoentities('NonApplicable');
					    $answer->pictogram   = 'N/A';
					    $answer->color       = '#2b2b2b';

					    $answer->create($user);
				    }
				}


				$urltogo = $backtopage ? str_replace('__ID__', $result, $backtopage) : $backurlforlist;
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $object->id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: ".$urltogo);
				exit;
			} else {
				// Creation KO
				if (!empty($object->errors)) {
					setEventMessages($object->error, $object->errors, 'errors');
				} else {
					setEventMessages($langs->trans($object->error), null, 'errors');
				}
				$action = 'create';
			}
		} else {
			$action = 'create';
		}
	}

	// Action to update record
	if ($action == 'update' && !empty($permissiontoadd)) {

		$previousType = $object->type;

		foreach ($object->fields as $key => $val) {
			// Check if field was submited to be edited
			if ($object->fields[$key]['type'] == 'duration') {
				if (!GETPOSTISSET($key.'hour') || !GETPOSTISSET($key.'min')) {
					continue; // The field was not submited to be saved
				}
			} elseif ($object->fields[$key]['type'] == 'boolean') {
				if (!GETPOSTISSET($key)) {
					$object->$key = 0; // use 0 instead null if the field is defined as not null
					continue;
				}
			} else {
				if (!GETPOSTISSET($key)) {
					continue; // The field was not submited to be saved
				}
			}
			// Ignore special fields
			if (in_array($key, array('rowid', 'entity', 'import_key'))) {
				continue;
			}
			if (in_array($key, array('date_creation', 'tms', 'fk_user_creat', 'fk_user_modif'))) {
				if (!in_array(abs($val['visible']), array(1, 3, 4))) {
					continue; // Only 1 and 3 and 4, that are cases to update
				}
			}

			// Set value to update
			if (preg_match('/^(text|html)/', $object->fields[$key]['type'])) {
				$tmparray = explode(':', $object->fields[$key]['type']);
				if (!empty($tmparray[1])) {
					$value = GETPOST($key, $tmparray[1]);
				} else {
					$value = GETPOST($key, 'restricthtml');
				}
			} elseif ($object->fields[$key]['type'] == 'date') {
				$value = dol_mktime(12, 0, 0, GETPOST($key.'month', 'int'), GETPOST($key.'day', 'int'), GETPOST($key.'year', 'int')); // for date without hour, we use gmt
			} elseif ($object->fields[$key]['type'] == 'datetime') {
				$value = dol_mktime(GETPOST($key.'hour', 'int'), GETPOST($key.'min', 'int'), GETPOST($key.'sec', 'int'), GETPOST($key.'month', 'int'), GETPOST($key.'day', 'int'), GETPOST($key.'year', 'int'), 'tzuserrel');
			} elseif ($object->fields[$key]['type'] == 'duration') {
				if (GETPOST($key.'hour', 'int') != '' || GETPOST($key.'min', 'int') != '') {
					$value = 60 * 60 * GETPOST($key.'hour', 'int') + 60 * GETPOST($key.'min', 'int');
				} else {
					$value = '';
				}
			} elseif (preg_match('/^(integer|price|real|double)/', $object->fields[$key]['type'])) {
				$value = price2num(GETPOST($key, 'alphanohtml')); // To fix decimal separator according to lang setup
			} elseif ($object->fields[$key]['type'] == 'boolean') {
				$value = ((GETPOST($key, 'aZ09') == 'on' || GETPOST($key, 'aZ09') == '1') ? 1 : 0);
			} elseif ($object->fields[$key]['type'] == 'reference') {
				$value = array_keys($object->param_list)[GETPOST($key)].','.GETPOST($key.'2');
			} else {
				if ($key == 'lang') {
					$value = GETPOST($key, 'aZ09');
				} else {
					$value = GETPOST($key, 'alphanohtml');
				}
			}
			if (preg_match('/^integer:/i', $object->fields[$key]['type']) && $value == '-1') {
				$value = ''; // This is an implicit foreign key field
			}
			if (!empty($object->fields[$key]['foreignkey']) && $value == '-1') {
				$value = ''; // This is an explicit foreign key field
			}

			$object->$key = $value;
			if ($val['notnull'] > 0 && $object->$key == '' && is_null($val['default'])) {
				$error++;
				setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv($val['label'])), null, 'errors');
			}

			// Validation of fields values
			if (getDolGlobalInt('MAIN_FEATURES_LEVEL') >= 2 || !empty($conf->global->MAIN_ACTIVATE_VALIDATION_RESULT)) {
				if (!$error && !empty($val['validate']) && is_callable(array($object, 'validateField'))) {
					if (!$object->validateField($object->fields, $key, $value)) {
						$error++;
					}
				}
			}

			if (isModEnabled('categorie')) {
				$categories = GETPOST('categories', 'array');
				if (method_exists($object, 'setCategories')) {
					$object->setCategories($categories);
				}
			}
		}

		// Fill array 'array_options' with data from add form
		if (!$error) {
			$ret = $extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET');
			if ($ret < 0) {
				$error++;
			}
		}

		if (!$error) {
			$action = 'view';

			$types = array('photo_ok', 'photo_ko');

			foreach ($types as $type) {
				$pathToTmpPhoto = $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $object->ref .'/' . $type;
				$photoList = dol_dir_list($conf->digiquali->multidir_output[$conf->entity] . '/question/' . $object->ref . '/' . $type, 'files');
				if (is_array($photoList) && !empty($photoList)) {
					$favoriteExists = 0;
					foreach ($photoList as $photo) {
						if ($photo['name'] == $object->$type) {
							$favoriteExists = 1;
						}
					}
					foreach ($photoList as $index => $photo) {
						if ($index == 0 && (dol_strlen($object->$type) == 0 || !$favoriteExists)) {
							$object->$type = $photo['name'];
						}
					}
				} else {
					$object->$type = '';
				}
			}
			$result = $object->update($user);

			$newType = $object->type;

			if ($result > 0) {
				if ($newType != $previousType && $newType != 'MultipleChoices' && $newType != 'UniqueChoice') {
					$answerList = $answer->fetchAll('ASC', 'position', 0, 0, ['fk_question' => $result]);

					if (is_array($answerList) && !empty($answerList)) {
						foreach($answerList as $linkedAnswer) {
							$linkedAnswer->delete($user, true, false);
						}
					}

					if ($object->type == 'OkKo' || $object->type == 'OkKoToFixNonApplicable') {
						$answer->fk_question = $result;
						$answer->value       = $langs->transnoentities('OK');
						$answer->pictogram   = 'check';
						$answer->color       = '#47e58e';

						$answer->create($user);

						$answer->fk_question = $result;
						$answer->value       = $langs->transnoentities('KO');
						$answer->pictogram   = 'times';
						$answer->color       = '#e05353';

						$answer->create($user);
					}

					if ($object->type == 'OkKoToFixNonApplicable') {
						$answer->fk_question = $result;
						$answer->value = $langs->transnoentities('ToFix');
						$answer->pictogram = 'tools';
						$answer->color = '#e9ad4f';

						$answer->create($user);

						$answer->fk_question = $result;
						$answer->value = $langs->transnoentities('NonApplicable');
						$answer->pictogram = 'N/A';
						$answer->color = '#2b2b2b';

						$answer->create($user);
					}
				}


			}

			$urltogo = $backtopage ? str_replace('__ID__', $result, $backtopage) : $backurlforlist;
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $object->id, $urltogo); // New method to autoselect project after a New on another form object creation
			if ($urltogo && !$noback) {
				header("Location: " . $urltogo);
				exit;
			}

		} else {
			$action = 'edit';
		}
	}

	// Action to delete
	if ($action == 'confirm_delete' && !empty($permissiontodelete)) {
		if (!($object->id > 0)) {
			dol_print_error('', 'Error, object must be fetched before being deleted');
			exit;
		}

		if (method_exists($object, 'isErasable') && $object->isErasable() <= 0) {
			$langs->load("errors");
			$object->errors = $langs->trans('ErrorQuestionUsedInSheet',$object->ref);
			$result = 0;
		} else {
			$result = $object->delete($user);
		}

		if ($result > 0) {
			// Delete OK
			setEventMessages("RecordDeleted", null, 'mesgs');

			header("Location: ".$backurlforlist);
			exit;
		} else {
			$error++;
			if (!empty($object->errors)) {
				setEventMessages(null, $object->errors, 'errors');
			} else {
				setEventMessages($object->error, null, 'errors');
			}
		}
		$action = '';
	}

	// Action clone object
	if ($action == 'confirm_clone' && $confirm == 'yes') {
		$options['label']      = GETPOST('clone_label');
		$options['photos']     = GETPOST('clone_photos');
		$options['categories'] = GETPOST('clone_categories');
		$result = $object->createFromClone($user, $object->id, $options);
		if ($result > 0) {
			header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $result);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = '';
		}
	}

	if ($action == 'addAnswer') {
		$answerValue = GETPOST('answerValue');
		$answerColor = GETPOST('answerColor');
		$answerPicto = GETPOST('answerPicto');

		if (empty($answerValue)) {
			setEventMessages($langs->trans('EmptyValue'), [], 'errors');
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo);
			header('Location: ' . $urltogo . '&answerValue='. $answerValue .'&answerPicto='. $answerPicto .'#answerList');
			exit;
		} else {
			$answer->value = $answerValue;
			$answer->color = $answerColor;
			$answer->pictogram = $answerPicto;
			$answer->fk_question = $id;

			$result = $answer->create($user);

			if ($result > 0) {
				setEventMessages($langs->trans('AnswerCreated'), []);
			} else {
				setEventMessages($langs->trans('ErrorCreateAnswer'), [], 'errors');
			}
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo);
			header('Location: ' . $urltogo . '#answerList');
			exit;
		}
	}

	if ($action == 'updateAnswer' && !$cancel) {
		$answerValue = GETPOST('answerValue');
		$answerColor = GETPOST('answerColor');
		$answerPicto = GETPOST('answerPicto');
		$answerId    = GETPOST('answerId');

		$answer->fetch($answerId);
		if (empty($answerValue)) {
			setEventMessages($langs->trans('EmptyValue'), [], 'errors');
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo);
			header('Location: ' . $urltogo . '&action=editAnswer&answerId='. $answerId .'&answerValue='. $answerValue .'&answerPicto='. $answerPicto . '#answerList');
			exit;
		} else {
			$answer->value = $answerValue;
			$answer->color = $answerColor;
			$answer->pictogram = $answerPicto;

			$result = $answer->update($user);

			if ($result > 0) {
				setEventMessages($langs->trans("AnswerUpdated"), [], 'mesgs');
			} else {
				setEventMessages($langs->trans('ErrorUpdateAnswer'), [], 'errors');
			}
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo);
			header('Location: ' . $urltogo . '#answerList');
			exit;
		}
	}

	if ($action == 'deleteAnswer') {
		$answerId = GETPOST('answerId');

		$answer->fetch($answerId);
		$result = $answer->delete($user);

		$urltogo = str_replace('__ID__', $result, $backtopage);
		$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo);
		header('Location: ' . $urltogo . '#answerList');
		if ($result > 0) {
			setEventMessages($langs->trans("AnswerDeleted"), [], 'mesgs');
		} else {
			setEventMessages($langs->trans('ErrorDeleteAnswer'), [], 'errors');
		}
	}

	if ($action == 'moveLine' && $permissiontoadd) {
		$idsArray = json_decode(file_get_contents('php://input'), true);
		if (is_array($idsArray['order']) && !empty($idsArray['order'])) {
			$ids = array_values($idsArray['order']);
			$reIndexedIds = array_combine(range(1, count($ids)), array_values($ids));
		}
		$object->updateAnswersPosition($reIndexedIds);
	}

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Action to set status STATUS_LOCKED
	if ($action == 'confirm_lock') {
		$object->fetch($id);
		if ( ! $error) {
			$result = $object->setLocked($user, false);
			if ($result > 0) {
				// Set locked OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			} else {
				// Set locked KO
				if ( ! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else setEventMessages($object->error, null, 'errors');
			}
		}
	}

	// Action to set status STATUS_ARCHIVED
	if ($action == 'confirm_archive' && $permissiontoadd) {
		$object->fetch($id);
		if (!$error) {
			$result = $object->setArchived($user);
			if ($result > 0) {
				// Set Archived OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header('Location: ' . $urltogo);
				exit;
			} elseif (!empty($object->errors)) { // Set Archived KO
				setEventMessages('', $object->errors, 'errors');
			} else {
				setEventMessages($object->error, [], 'errors');
			}
		}
	}
}

/*
 * View
 */

$title    = $langs->trans(ucfirst($object->element));
$help_url = 'FR:Module_DigiQuali';
$moreJS   = ['/saturne/js/includes/hammer.min.js'];

saturne_header(1,'', $title, $help_url, '', 0, 0, $moreJS);

// Part to create
if ($action == 'create') {
	print load_fiche_titre($langs->trans('NewQuestion'), '', 'object_'.$object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="createQuestionForm" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldcreate question-table">'."\n";

	// Label -- Libellé
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
	print '<input class="flat" type="text" size="36" name="label" id="label" value="'.GETPOST('label').'">';
	print '</td></tr>';

	// Type -- Type
	print '<tr><td class="fieldrequired"><label class="" for="type">' . $langs->trans("QuestionType") . '</label></td><td>';
	print saturne_select_dictionary('type','c_question_type', 'ref', 'label', GETPOST('type') ?: 'OkKoToFixNonApplicable');
	print '</td></tr>';

	// Description -- Description
	print '<tr><td class=""><label class="" for="description">' . $langs->trans("Description") . '</label></td><td>';
	$doleditor = new DolEditor('description', GETPOST('description'), '', 90, 'dolibarr_details', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';

	// EnterComment -- Saisir les commentaires
	print '<tr><td class="minwidth400">' . $langs->trans("EnterComment") . '</td><td>';
	print '<input type="checkbox" id="enter_comment" name="enter_comment"' . (GETPOST('enter_comment') ? ' checked=""' : '') . '>';
	print $form->textwithpicto('', $langs->trans('EnterCommentTooltip'));
	print '</td></tr>';

	// AuthorizeAnswerPhoto -- Utiliser des réponses de photos
	print '<tr><td class="minwidth400">' . $langs->trans("AuthorizeAnswerPhoto") . '</td><td>';
	print '<input type="checkbox" id="authorize_answer_photo" name="authorize_answer_photo"' . (GETPOST('authorize_answer_photo') ? ' checked=""' : '') . '>';
	print $form->textwithpicto('', $langs->trans('AuthorizeAnswerPhotoTooltip'));
	print '</td></tr>';

	// ShowPhoto -- Utiliser des photos
	print '<tr><td class="minwidth400">' . $langs->trans("ShowPhoto") . '</td><td>';
	print '<input type="checkbox" id="show_photo" name="show_photo"' . (GETPOST('show_photo') ? ' checked=""' : '') . '>';
	print $form->textwithpicto('', $langs->trans('ShowPhotoTooltip'));
	print '</td></tr>';

	// Photo OK -- Photo OK
	print '<tr class="linked-medias photo_ok hidden" ' . (GETPOST('show_photo') ? '' : 'style="display:none"') . '><td class=""><label for="photo_ok">' . $langs->trans("PhotoOk") . '</label></td><td class="linked-medias-list">'; ?>
	<input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-photo-ok" type="file" name="userfile[]" capture="environment" accept="image/*">
    <input type="hidden" class="fast-upload-options" data-from-subtype="photo_ok" data-from-subdir="photo_ok"/>
	<label for="fast-upload-photo-ok">
		<div class="wpeo-button button-square-50">
			<i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
		</div>
	</label>
	<input type="hidden" class="favorite-photo" id="photo_ok" name="photo_ok" value="<?php echo GETPOST('favorite_photo_ok') ?>"/>
	<div class="wpeo-button button-square-50 open-media-gallery add-media modal-open" value="0">
		<input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo 0 ?>" data-from-type="question" data-from-subtype="photo_ok" data-from-subdir="photo_ok"/>
		<i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
	</div>
	<?php
	$relativepath = 'digiquali/medias/thumbs';
	print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/tmp/QU0/photo_ok', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'question/tmp/QU0/photo_ok', $object, 'photo_ok', 1, $permissiontodelete);
	print '</td></tr>';

	print '<tr></tr>';

	// Photo KO -- Photo KO
	print '<tr class="linked-medias photo_ko hidden" ' . (GETPOST('show_photo') ? '' : 'style="display:none"') . '><td class=""><label for="photo_ko">' . $langs->trans("PhotoKo") . '</label></td><td class="linked-medias-list">'; ?>
	<input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-photo-ko" type="file" name="userfile[]" capture="environment" accept="image/*">
    <input type="hidden" class="fast-upload-options" data-from-subtype="photo_ko" data-from-subdir="photo_ko"/>
	<label for="fast-upload-photo-ko">
		<div class="wpeo-button button-square-50">
			<i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
		</div>
	</label>
	<input type="hidden" class="favorite-photo" id="photo_ko" name="photo_ko" value="<?php echo GETPOST('favorite_photo_ko') ?>"/>
	<div class="wpeo-button button-square-50 open-media-gallery add-media modal-open" value="0">
        <input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo 0 ?>" data-from-type="question" data-from-subtype="photo_ko" data-from-subdir="photo_ko"/>
		<i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
	</div>
	<?php
	print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/tmp/QU0/photo_ko', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'question/tmp/QU0/photo_ko', $object, 'photo_ko', 1, $permissiontodelete);
	print '</td></tr>';

	// Categories
	if (!empty($conf->categorie->enabled)) {
		print '<tr><td>'.$langs->trans("Categories").'</td><td>';
		$categoryArborescence = $form->select_all_categories('question', '', 'parent', 64, 0, 1);
		print img_picto('', 'category', 'class="pictofixedwidth"').$form::multiselectarray('categories', $categoryArborescence, GETPOST('categories', 'array'), '', 0, 'minwidth100imp maxwidth500 widthcentpercentminusxx');
        print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/categories/index.php?type=question&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?action=create') . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddCategories') . '"></span></a>';
		print "</td></tr>";
	}

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button wpeo-button" name="add" value="'.dol_escape_htmltag($langs->trans("Create")).'">';
	print '&nbsp; ';
	print ' &nbsp; <input type="button" id ="actionButtonCancelCreate" class="button" name="cancel" value="' . $langs->trans("Cancel") . '" onClick="javascript:history.go(-1)">';
	print '</div>';

	print '</form>';

	dol_set_focus('input[name="label"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("ModifyQuestion"), '', $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit question-table">'."\n";

	// Ref -- Ref
	print '<tr><td class="fieldrequired">' . $langs->trans("Ref") . '</td><td>';
	print $object->ref;
	print '</td></tr>';

	//Label -- Libellé
	print '<tr><td class="fieldrequired minwidth400">'.$langs->trans("Label").'</td><td>';
	print '<input class="flat" type="text" size="36" name="label" id="label" value="'.$object->label.'">';
	print '</td></tr>';

	// Type -- Type
	print '<tr><td class="fieldrequired"><label class="" for="type">' . $langs->trans("QuestionType") . '</label></td><td>';
	print saturne_select_dictionary('type','c_question_type', 'ref', 'label', $object->type);
	print '</td></tr>';

	//Description -- Description
	print '<tr><td><label class="" for="description">' . $langs->trans("Description") . '</label></td><td>';
	$doleditor = new DolEditor('description', $object->description, '', 90, 'dolibarr_details', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';

	// EnterComment -- Saisir les commentaires
	print '<tr class="oddeven"><td class="minwidth400">';
	print $langs->trans("EnterComment");
	print '</td>';
	print '<td>';
	print '<input type="checkbox" id="enter_comment" name="enter_comment"' . ($object->enter_comment ? ' checked=""' : '') . '"> ';
	print $form->textwithpicto('', $langs->trans('EnterCommentTooltip'));
	print '</td></tr>';

	// AuthorizeAnswerPhoto -- Utiliser les réponses de photos
	print '<tr class="oddeven"><td class="minwidth400">';
	print $langs->trans("AuthorizeAnswerPhoto");
	print '</td>';
	print '<td>';
	print '<input type="checkbox" id="authorize_answer_photo" name="authorize_answer_photo"' . ($object->authorize_answer_photo ? ' checked=""' : '') . '"> ';
	print $form->textwithpicto('', $langs->trans('AuthorizeAnswerPhotoTooltip'));
	print '</td></tr>';

	// ShowPhoto -- Utiliser les photos
	print '<tr class="oddeven"><td class="minwidth400">';
	print $langs->trans("ShowPhoto");
	print '</td>';
	print '<td>';
	print '<input type="checkbox" id="show_photo" name="show_photo"' . ($object->show_photo ? ' checked=""' : '') . '"> ';
	print $form->textwithpicto('', $langs->trans('ShowPhotoTooltip'));
	print '</td></tr>';

	// Photo OK -- Photo OK
	print '<tr class="' . ($object->show_photo ? ' linked-medias photo_ok' : ' linked-medias photo_ok hidden' ) . '" style="' . ($object->show_photo ? ' ' : ' display:none') . '"><td><label for="photo_ok">' . $langs->trans("PhotoOk") . '</label></td><td class="linked-medias-list">'; ?>
	<input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-photo-ok" type="file" name="userfile[]" capture="environment" accept="image/*">
    <input type="hidden" class="fast-upload-options" data-from-subtype="photo_ok" data-from-subdir="photo_ok"/>
	<label for="fast-upload-photo-ok">
		<div class="wpeo-button button-square-50">
			<i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
		</div>
	</label>
	<input type="hidden" class="favorite-photo" id="photo_ok" name="photo_ok" value="<?php echo (dol_strlen($object->photo_ok) > 0 ? $object->photo_ok : GETPOST('favorite_photo_ok')) ?>"/>
	<div class="wpeo-button button-square-50 open-media-gallery add-media modal-open" value="0">
        <input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo $object->id ?>" data-from-type="question" data-from-subtype="photo_ok" data-from-subdir="photo_ok"/>
		<i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
	</div>
	<?php
	$relativepath = 'digiquali/medias/thumbs';
	print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $object->ref . '/photo_ok', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'question/'. $object->ref . '/photo_ok', $object, 'photo_ok', 1, $permissiontodelete);
	print '</td></tr>';

	print '<tr></tr>';

	// Photo KO -- Photo KO
	print '<tr class="' . ($object->show_photo ? ' linked-medias photo_ko' : ' linked-medias photo_ko hidden' ) . '" style="' . ($object->show_photo ? ' ' : ' display:none') . '"><td><label for="photo_ko">' . $langs->trans("PhotoKo") . '</label></td><td class="linked-medias-list">'; ?>
	<input hidden multiple class="fast-upload<?php echo getDolGlobalInt('SATURNE_USE_FAST_UPLOAD_IMPROVEMENT') ? '-improvement' : ''; ?>" id="fast-upload-photo-ko" type="file" name="userfile[]" capture="environment" accept="image/*">
    <input type="hidden" class="fast-upload-options" data-from-subtype="photo_ko" data-from-subdir="photo_ko"/>
	<label for="fast-upload-photo-ko">
		<div class="wpeo-button button-square-50">
			<i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
		</div>
	</label>
	<input type="hidden" class="favorite-photo" id="photo_ko" name="photo_ko" value="<?php echo (dol_strlen($object->photo_ko) > 0 ? $object->photo_ko : GETPOST('favorite_photo_ko')) ?>"/>
	<div class="wpeo-button button-square-50 open-media-gallery add-media modal-open" value="0">
        <input type="hidden" class="modal-options" data-modal-to-open="media_gallery" data-from-id="<?php echo $object->id ?>" data-from-type="question" data-from-subtype="photo_ko" data-from-subdir="photo_ko"/>
		<i class="fas fa-folder-open"></i><i class="fas fa-plus-circle button-add"></i>
	</div>
	<?php
	print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $object->ref . '/photo_ko', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'question/'. $object->ref . '/photo_ko', $object, 'photo_ko', 1, $permissiontodelete);
	print '</td></tr>';

	// Tags-Categories
	if ($conf->categorie->enabled) {
		print '<tr><td>'.$langs->trans("Categories").'</td><td>';
		$categoryArborescence = $form->select_all_categories('question', '', 'parent', 64, 0, 1);
		$c = new Categorie($db);
		$cats = $c->containing($object->id, 'question');
		$arrayselected = array();
		if (is_array($cats)) {
			foreach ($cats as $cat) {
				$arrayselected[] = $cat->id;
			}
		}
		print img_picto('', 'category', 'class="pictofixedwidth"').$form::multiselectarray('categories', $categoryArborescence, (GETPOSTISSET('categories') ? GETPOST('categories', 'array') : $arrayselected), '', 0, 'minwidth100imp maxwidth500 widthcentpercentminusxx');
        print '<a class="butActionNew" href="' . DOL_URL_ROOT . '/categories/index.php?type=question&backtopage=' . urlencode($_SERVER['PHP_SELF'] . '?action=create') . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddCategories') . '"></span></a>';
		print "</td></tr>";
	}

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center"><input type="submit" class="button button-save wpeo-button" name="save" value="'.$langs->trans("Save").'">';
	print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	saturne_get_fiche_head($object, 'card', $title);
	saturne_banner_tab($object);

	$formconfirm = '';

	// Lock confirmation
	if (($action == 'lock' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
		$formconfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('LockObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmLockObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_lock', '', 'yes', 'actionButtonLock', 350, 600);
	}

	// Clone confirmation
	if (($action == 'clone' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
		// Define confirmation messages
		$formquestionclone = [
			['type' => 'text', 'name' => 'clone_label', 'label' => $langs->trans('NewLabelForClone', $langs->transnoentities('The' . ucfirst($object->element))), 'value' => $langs->trans('CopyOf') . ' ' . $object->ref, 'size' => 24],
			['type' => 'checkbox', 'name' => 'clone_photos', 'label' => $langs->trans('ClonePhotos'), 'value' => 1],
			['type' => 'checkbox', 'name' => 'clone_categories', 'label' => $langs->trans('CloneCategories'), 'value' => 1],
		];
		$formconfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('CloneObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmCloneObject', $langs->transnoentities('The' . ucfirst($object->element)), $object->ref), 'confirm_clone', $formquestionclone, 'yes', 'actionButtonClone', 350, 600);
	}

	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('Delete') . ' ' . $langs->transnoentities('The'  . ucfirst($object->element)), $langs->trans('ConfirmDeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_delete', '', 'yes', 1);
	}

	// Call Hook formConfirm
	$parameters = ['formConfirm' => $formconfirm];
	$reshook    = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';

	//Description -- Description
	print '<tr><td class="titlefield">';
	print $langs->trans("Description");
	print '</td>';
	print '<td>';
	print $object->description;
	print '</td></tr>';

	// Type -- Type
	print '<tr><td class="titlefield">';
	print $langs->trans("QuestionType");
	print '</td>';
	print '<td>';
	print $langs->transnoentities($object->type);
	print '</td></tr>';

	// EnterComment -- Saisir les commentaires
	print '<tr><td class="titlefield">';
	print $langs->trans("EnterComment");
	print '</td>';
	print '<td>';
	print '<input type="checkbox" id="enter_comment" name="enter_comment"' . ($object->enter_comment ? ' checked=""' : '') . '" disabled> ';
	print '</td></tr>';

	// AuthorizeAnswerPhoto -- Utiliser les réponses de photos
	print '<tr><td class="titlefield">';
	print $langs->trans("AuthorizeAnswerPhoto");
	print '</td>';
	print '<td>';
	print '<input type="checkbox" id="authorize_answer_photo" name="authorize_answer_photo"' . ($object->authorize_answer_photo ? ' checked=""' : '') . '" disabled> ';
	print '</td></tr>';

	// ShowPhoto -- Utiliser les photos
	print '<tr><td class="titlefield">';
	print $langs->trans("ShowPhoto");
	print '</td>';
	print '<td>';
	print '<input type="checkbox" id="show_photo" name="show_photo"' . ($object->show_photo ? ' checked=""' : '') . '" disabled> ';
	print '</td></tr>';

	if ($object->show_photo > 0) {
		//Photo OK -- Photo OK
		print '<tr><td class="titlefield">';
		print $langs->trans("PhotoOk");
		print '</td>';
		print '<td>';
		print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $object->ref . '/photo_ok', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'question/'. $object->ref . '/photo_ok', $object, 'photo_ok', 0, 0, 0,1);
		print '</td></tr>';

		//Photo KO -- Photo KO
		print '<tr><td class="titlefield">';
		print $langs->trans("PhotoKo");
		print '</td>';
		print '<td>';
		print saturne_show_medias_linked('digiquali', $conf->digiquali->multidir_output[$conf->entity] . '/question/'. $object->ref . '/photo_ko', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'question/'. $object->ref . '/photo_ko', $object, 'photo_ko', 0, 0, 0,1);
		print '</td></tr>';
	}

	// Categories
	if ($conf->categorie->enabled) {
		print '<tr><td class="valignmiddle">'.$langs->trans("Categories").'</td><td>';
		print $form->showCategories($object->id, 'question', 1);
		print "</td></tr>";
	}

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

    $answerList = $answer->fetchAll('ASC', 'position', 0, 0, ['customsql' => 't.status > ' . Answer::STATUS_DELETED . ' AND t.fk_question = ' . $object->id]);

	// Buttons for actions
	if ($action != 'presend') {
		print '<div class="tabsAction">';
		$parameters = [];
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook) && $permissiontoadd) {
			// Modify
			if ($object->status == $object::STATUS_VALIDATED) {
				print '<a class="butAction" id="actionButtonEdit" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=edit' . '"><i class="fas fa-edit"></i> ' . $langs->trans('Modify') . '</a>';
			} else {
				print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '"><i class="fas fa-edit"></i> ' . $langs->trans('Modify') . '</span>';
			}

			// Lock
			if (($object->type == 'UniqueChoice' || $object->type == 'MultipleChoices') && empty($answerList)) {
				print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('AnswerMustBeCreated')) . '"><i class="fas fa-lock"></i> ' . $langs->trans('Lock') . '</span>';
			} else if ($object->status == $object::STATUS_VALIDATED) {
				print '<span class="butAction" id="actionButtonLock"><i class="fas fa-lock"></i> ' . $langs->trans('Lock') . '</span>';
			} else {
				print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidated', $langs->transnoentities('The' . ucfirst($object->element)))) . '"><i class="fas fa-lock"></i> ' . $langs->trans('Lock') . '</span>';
			}

			// Archive
			if ($object->status == $object::STATUS_LOCKED) {
				print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=confirm_archive&token=' . newToken() . '"><i class="fas fa-archive"></i> ' . $langs->trans('Archive') . '</a>';
			} else {
				print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToArchive', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '"><i class="fas fa-archive"></i> ' . $langs->trans('Archive') . '</span>';
			}

			// Clone
			print '<span class="butAction" id="actionButtonClone"><i class="fas fa-clone"></i> ' . $langs->trans('Clone') . '</span>';

			// Delete (need delete permission, or if draft, just need create/modify permission)
			print dolGetButtonAction('<i class="fas fa-trash"></i> ' . $langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete', '', $permissiontodelete || ($object->status == $object::STATUS_DRAFT && $permissiontoadd));
		}
		print '</div>';
	}

	//if ($object->type == 'MultipleChoices' || $object->type == 'UniqueChoice' || $object->type == 'OkKo' || $object->type == 'OkKoToFixNonApplicable') {

		$pictosArray = get_answer_pictos_array();

		// ANSWERS LINES
		print '<div class="div-table-responsive-no-min" style="overflow-x: unset !important">';
		print load_fiche_titre($langs->trans("AnswersList"), '', '', 0, 'answerList');
		print '<table id="tablelines" class="centpercent noborder noshadow">';
		global $forceall, $forcetoshowtitlelines;

		if (empty($forceall)) $forceall = 0;

		// Define colspan for the button 'Add'
		$colspan = 3;
		?>
		<script>
			$(document).ready(function(){
				$(".move-line").css("background-image",'url(<?php echo DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/grip.png'; ?>)');
				$(".move-line").css("background-repeat","no-repeat");
				$(".move-line").css("background-position","center center");
			});
		</script>
		<?php
		// Lines
		print '<thead><tr class="liste_titre">';
		print '<td>' . $langs->trans('Ref') . '</td>';
		print '<td>' . $langs->trans('Value') . '</td>';
		print '<td class="center">' . $langs->trans('Picto') . '</td>';
		print '<td class="center">' . $langs->trans('Color') . '</td>';
		print '<td class="center">' . $langs->trans('Action') . '</td>';
		print '<td class="center"></td>';
		print '</tr></thead>';

		if (is_array($answerList) && !empty($answerList)) {
			foreach($answerList as $answerSingle) {
				if ($action == 'editAnswer' && GETPOST('answerId') == $answerSingle->id) {
					//EDIT LINE
					print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '">';
					print '<input type="hidden" name="token" value="' . newToken() . '">';
					print '<input type="hidden" name="action" value="updateAnswer">';
					print '<input type="hidden" name="answerId" value="' . $answerSingle->id . '">';

					print '<tr id="'. $answerSingle->id .'" class="line-row oddeven">';
					print '<td>';
					print img_picto('', $answerSingle->picto, 'class="pictofixedwidth"') . $answerSingle->ref;
					print '</td>';

					print '<td>';
					print '<input name="answerValue" value="'. (GETPOST('answerValue') ?: $answerSingle->value) .'">';
					print '</td>';

					// Pictogram -- Pictogram
					print '<td class="center">';
					print answer_pictos_dropdown(GETPOST('answerPicto') ?: $answerSingle->pictogram);
					print '</td>';

					print '<td class="center">';
					print '<input type="color" name="answerColor" value="' . $answerSingle->color . '">';
					print '</td>';

					print '<td class="center">';
					print $form->buttonsSaveCancel();
					print '</td>';

					if ($object->status < $object::STATUS_LOCKED) {
						print '<td class="move-line ui-sortable-handle">';
					} else {
						print '<td>';
					}
					print '</td>';
					print '</tr>';
					print '</form>';
				} else {
					//SHOW LINE
					print '<tr id="'. $answerSingle->id .'" class="line-row oddeven">';
					print '<td>';
					print img_picto('', $answerSingle->picto, 'class="pictofixedwidth"') . $answerSingle->ref;
					print '</td>';

					print '<td>';
					print $answerSingle->value;
					print '</td>';

					print '<td class="center">';
					print $pictosArray[$answerSingle->pictogram]['picto_source'];
					print '</td>';

					print '<td class="center">';
					print '<span class="color-circle" style="background:'. $answerSingle->color .'; color:'. $answerSingle->color .';">';
					print '</span>';
					print '</td>';
					print '<td class="center">';
					if ($object->status < Question::STATUS_LOCKED && ($object->type != 'OkKo' && $object->type != 'OkKoToFixNonApplicable')) {
						print '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . $id . '&amp;action=editAnswer&answerId=' . $answerSingle->id . '#answerList">';
						print '<div class="wpeo-button button-grey">';
						print img_edit();
						print '</div>';
						print '</a>';

						print '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . $id . '&amp;action=deleteAnswer&answerId=' . $answerSingle->id . '&token='. newToken() .'">';
						print '<div class="wpeo-button button-grey" style="margin-left: 10px">';
						print img_delete();
						print '</div>';
						print '</a>';
						print '</td>';
						print '<td class="move-line ui-sortable-handle">';
					} else {
						print '</td>';
						print '<td>';
					}
					print '</td>';
					print '</tr>';
				}
			}
		}

		if ($object->status < QUESTION::STATUS_LOCKED && ($object->type != 'OkKo' && $object->type != 'OkKoToFixNonApplicable')) {
			print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="action" value="addAnswer">';
			print '<input type="hidden" name="id" value="' . $id . '">';

			print '<tr>';

			print '<td>-</td>';
			print '<td><input name="answerValue" value=""></td>';

			// Pictogram -- Pictogram
			print '<td class="center">';
			print answer_pictos_dropdown(GETPOST('answerPicto') ?: '');
			print '</td>';
			?>

			<td class="center">
				<input type="color" name="answerColor" class="new-answer-color" value="<?php echo GETPOST('answerColor'); ?>">
			</td>
			<script>
				var randomColor = Math.floor(Math.random()*16777215).toString(16);
				$('.new-answer-color').val('#' + randomColor)
			</script>
			<?php


			print '<td class="center">';
			print '<input type="submit" class="button wpeo-button" value="' . $langs->trans("Add") . '">';
			print '</td>';
			print '<td>';
			print '</td>';
			print '</tr>';

			print '</table>';
			print '</form>';
			print '</div>';
		}
	//}
	print dol_get_fiche_end();

	print '<div class="fichecenter"><div class="fichehalfright">';

	$maxEvent = 10;

	$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/saturne/view/saturne_agenda.php', 1) . '?id=' . $object->id . '&module_name=DigiQuali&object_type=' . $object->element);

	// List of actions on element
	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
	$formactions = new FormActions($db);
	$somethingshown = $formactions->showactions($object, $object->element . '@' . $object->module, '', 1, '', $MAXEVENT, '', $morehtmlcenter);

	print '</div></div>';
}

// End of page
llxFooter();
$db->close();
