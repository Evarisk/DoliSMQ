<?php
/* Copyright (C) 2022 EVARISK <technique@evarisk.com>
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
 * 	\defgroup   digiquali     Module DigiQuali
 *  \brief      DigiQuali module descriptor.
 *
 *  \file       core/modules/modDigiQuali.class.php
 *  \ingroup    digiquali
 *  \brief      Description and activation file for module DigiQuali
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module DigiQuali
 */
class modDigiQuali extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (file_exists(__DIR__ . '/../../../saturne/lib/saturne_functions.lib.php')) {
			require_once __DIR__ . '/../../../saturne/lib/saturne_functions.lib.php';
			saturne_load_langs(['digiquali@digiquali']);
		} else {
			$this->error++;
			$this->errors[] = $langs->trans('activateModuleDependNotSatisfied', 'DigiQuali', 'Saturne');
		}

		// Id for module (must be unique).
		$this->numero = 436301;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'digiquali';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = '';

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		$this->familyinfo = ['Evarisk' => ['position' => '01', 'label' => $langs->trans('Evarisk')]];
		// Module label (no space allowed), used if translation string 'ModuleDigiQualiName' not found (DigiQuali is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleDigiQualiDesc' not found (DigiQuali is name of module).
		$this->description = $langs->trans('DigiQualiDescription');
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = $langs->trans('DigiQualiDescriptionLong');

		// Author
		$this->editor_name = 'Evarisk';
		$this->editor_url = 'https://evarisk.com/';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.13.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where DIGIQUALI is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'digiquali_color@digiquali';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = [
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 1,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models' directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => ['/digiquali/css/scss/modules/_menu.min.css'],
			// Set this to relative path of js file if module must load a js on all pages
			'js' => [
				//   '/digiquali/js/digiquali.js',
			],
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			'hooks' => [
				'category',
				'categoryindex',
				'mainloginpage',
                'controlcard',
                'publiccontrol',
                'publicsurvey',
                'digiqualiadmindocuments',
                'projecttaskscard',
                'main',
                'controladmin',
                'surveyadmin',
			],
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		];

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/digiquali/temp","/digiquali/subdir");
		$this->dirs = [
			'/digiquali/temp',
			'/digiquali/question',
			'/ecm/digiquali',
			'/ecm/digiquali/medias',
			'/ecm/digiquali/controldocument',
			'/ecm/digiquali/surveydocument'
		];

		// Config pages. Put here list of php page, stored into digiquali/admin directory, to use to set up module.
		$this->config_page_url = ['setup.php@digiquali'];

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->depends = ['modFckeditor', 'modProduct', 'modProductBatch', 'modECM', 'modProjet', 'modCategorie', 'modSaturne', 'modTicket', 'modCron'];
		$this->requiredby = []; // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = []; // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = ['digiquali@digiquali'];

		// Prerequisites
		$this->phpmin = [7, 4]; // Minimum version of PHP required by module
		$this->need_dolibarr_version = [17, 0]; // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = []; // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = []; // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		//$this->automatic_activation = array('FR'=>'DigiQualiWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('DIGIQUALI_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('DIGIQUALI_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$i = 0;
		$this->const = [
			// CONST SHEET
			$i++ => ['DIGIQUALI_SHEET_ADDON', 'chaine', 'mod_sheet_standard', '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_TAGS_SET', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_UNIQUE_LINKED_ELEMENT', 'integer', 1, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_DISPLAY_MEDIAS', 'integer', 1, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_PRODUCT', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_PRODUCTLOT', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_USER', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_THIRDPARTY', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_CONTACT', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_PROJECT', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_LINK_TASK', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_INVOICE', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_ORDER', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_CONTRACT', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_TICKET', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_ENTREPOT', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_EXPEDITION', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_LINK_PROPAL', 'integer', 0, '', 0, 'current'],
//            $i++ => ['DIGIQUALI_SHEET_LINK_SUPPLIER_PROPOSAL', 'integer', 0, '', 0, 'current'],
//            $i++ => ['DIGIQUALI_SHEET_LINK_SUPPLIER_ORDER', 'integer', 0, '', 0, 'current'],
//            $i++ => ['DIGIQUALI_SHEET_LINK_SUPPLIER_INVOICE', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHEET_DEFAULT_TAG', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHEET_BACKWARD_COMPATIBILITY', 'integer', 0, '', 0, 'current'],

			// CONST QUESTION
			$i++ => ['DIGIQUALI_QUESTION_ADDON', 'chaine', 'mod_question_standard', '', 0, 'current'],
            $i++ => ['DIGIQUALI_QUESTION_BACKWARD_COMPATIBILITY', 'integer', 1, '', 0, 'current'],

			// CONST ANSWER
			$i++ => ['DIGIQUALI_ANSWER_ADDON', 'chaine', 'mod_answer_standard', '', 0, 'current'],

			// CONST CONTROL
			$i++ => ['DIGIQUALI_CONTROL_ADDON', 'chaine', 'mod_control_standard', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROL_USE_LARGE_MEDIA_IN_GALLERY', 'integer', 1, '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROL_REMINDER_ENABLED', 'integer', 1, '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROL_REMINDER_FREQUENCY', 'chaine', '30,60,90', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROL_REMINDER_TYPE', 'chaine', 'browser', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROL_BACKWARD_COMPATIBILITY', 'integer', 0, '', 0, 'current'],
			$i++ => ['PRODUCT_LOT_ENABLE_QUALITY_CONTROL', 'integer', 1, '', 0, 'current'],
            $i++ => ['DIGIQUALI_LOCK_CONTROL_OUTDATED_EQUIPMENT', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_ENABLE_PUBLIC_CONTROL_HISTORY', 'integer', 1, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHOW_QC_FREQUENCY_PUBLIC_INTERFACE', 'integer', 1, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHOW_LAST_CONTROL_FIRST_ON_PUBLIC_HISTORY', 'integer', 1, '', 0, 'current'],

            // CONST SURVEY
            $i++ => ['DIGIQUALI_SURVEY_ADDON', 'chaine', 'mod_survey_standard', '', 0, 'current'],
            $i++ => ['DIGIQUALI_SURVEY_USE_LARGE_MEDIA_IN_GALLERY', 'integer', 1, '', 0, 'current'],

            // CONST DIGIQUALI DOCUMENTS
            $i++ => ['DIGIQUALI_AUTOMATIC_PDF_GENERATION', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_MANUAL_PDF_GENERATION', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_SHOW_SIGNATURE_SPECIMEN', 'integer', 0, '', 0, 'current'],

			//CONST CONTROL DOCUMENT
			$i++ => ['DIGIQUALI_CONTROLDOCUMENT_ADDON', 'chaine', 'mod_controldocument_standard', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROLDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/digiquali/documents/doctemplates/controldocument/', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROLDOCUMENT_CUSTOM_ADDON_ODT_PATH', 'chaine', 'DOL_DATA_ROOT' . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'ecm/digiquali/controldocument/', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROLDOCUMENT_DEFAULT_MODEL', 'chaine', 'template_controldocument_photo' ,'', 0, 'current'],
			$i++ => ['DIGIQUALI_DOCUMENT_MEDIA_VIGNETTE_USED', 'chaine', 'small','', 0, 'current'],

            //CONST SURVEY DOCUMENT
            $i++ => ['DIGIQUALI_SURVEYDOCUMENT_ADDON', 'chaine', 'mod_surveydocument_standard', '', 0, 'current'],
            $i++ => ['DIGIQUALI_SURVEYDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/digiquali/documents/doctemplates/surveydocument/', '', 0, 'current'],
            $i++ => ['DIGIQUALI_SURVEYDOCUMENT_CUSTOM_ADDON_ODT_PATH', 'chaine', 'DOL_DATA_ROOT' . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'ecm/digiquali/surveydocument/', '', 0, 'current'],
            $i++ => ['DIGIQUALI_SURVEYDOCUMENT_DEFAULT_MODEL', 'chaine', 'template_surveydocument_photo' ,'', 0, 'current'],
            //$i++ => ['DIGIQUALI_SURVEYDOCUMENT_DISPLAY_MEDIAS', 'integer', 1,'', 0, 'current'],

			// CONST CONTROL LINE
			$i++ => ['DIGIQUALI_CONTROLDET_ADDON', 'chaine', 'mod_controldet_standard', '', 0, 'current'],
			$i++ => ['DIGIQUALI_CONTROLDET_AUTO_SAVE_ACTION', 'integer', 1, '', 0, 'current'],

			// CONST CONTROL EQUIPMENT
			$i++ => ['DIGIQUALI_CONTROL_EQUIPMENT_ADDON', 'chaine', 'mod_control_equipment_standard', '', 0, 'current'],

            // CONST SURVEY LINE
            $i++ => ['DIGIQUALI_SURVEYDET_ADDON', 'chaine', 'mod_surveydet_standard', '', 0, 'current'],
            $i++ => ['DIGIQUALI_SURVEYDET_AUTO_SAVE_ACTION', 'integer', 1, '', 0, 'current'],

			// CONST MODULE
			$i++ => ['DIGIQUALI_VERSION','chaine', $this->version, '', 0, 'current'],
			$i++ => ['DIGIQUALI_DB_VERSION', 'chaine', $this->version, '', 0, 'current'],
			$i++ => ['DIGIQUALI_SHOW_PATCH_NOTE', 'integer', 1, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_WIDTH_MINI', 'integer', 128, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_HEIGHT_MINI', 'integer', 72, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_WIDTH_SMALL', 'integer', 480, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_HEIGHT_SMALL', 'integer', 270, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_WIDTH_MEDIUM', 'integer', 854, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_HEIGHT_MEDIUM', 'integer', 480, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_WIDTH_LARGE', 'integer', 1280, '', 0, 'current'],
			$i++ => ['DIGIQUALI_MEDIA_MAX_HEIGHT_LARGE', 'integer', 720, '', 0, 'current'],
			$i++ => ['DIGIQUALI_DISPLAY_NUMBER_MEDIA_GALLERY', 'integer', 8, '', 0, 'current'],
            $i++ => ['DIGIQUALI_REDIRECT_AFTER_CONNECTION', 'integer', 0, '', 0, 'current'],
			$i++ => ['DIGIQUALI_ADVANCED_TRIGGER', 'integer', 1, '', 0, 'current'],
			$i++ => ['DIGIQUALI_DOCUMENT_DIRECTORIES_NAME_BACKWARD_COMPATIBILITY', 'integer', 0, '', 0, 'current'],
            $i++ => ['DIGIQUALI_ANSWER_PUBLIC_INTERFACE_TITLE', 'chaine', $langs->trans('AnswerPublicInterface'), '', 0, 'current'],

            $i++ => ['AGENDA_REMINDER_BROWSER', 'integer', 1, '', 0, 'current'],
            $i++ => ['AGENDA_REMINDER_EMAIL', 'integer', 1, '', 0, 'current'],

			// CONST DOCUMENTS
			$i++ => ['MAIN_ODT_AS_PDF', 'chaine', 'libreoffice', '', 0, 'current'],
		];

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isset($conf->digiquali) || !isset($conf->digiquali->enabled)) {
			$conf->digiquali = new stdClass();
			$conf->digiquali->enabled = 0;
		}

		// Array to add new pages in new tabs
		require_once __DIR__ . '/../../lib/digiquali_sheet.lib.php';

		$this->tabs   = [];
		$pictopath    = dol_buildpath('/custom/digiquali/img/digiquali_color.png', 1);
		$pictoDigiQuali = img_picto('', $pictopath, '', 1, 0, 0, '', 'pictoModule');
		$linkableElements = get_sheet_linkable_objects();

		if (is_array($linkableElements) && !empty($linkableElements)) {
			foreach($linkableElements as $linkableElementType => $linkableElement) {
                if (preg_match('/_/', $linkableElementType)) {
					$splittedElementType = preg_split('/_/', $linkableElementType);
					$moduleName = $splittedElementType[0];
					$objectName = strtolower($linkableElement['className']);
					$objectType = $objectName . '@' . $moduleName;
				} else {
                    $objectType = $linkableElement['tab_type'];
                }
				$this->tabs[] = ['data' => $objectType . ':+control:' . $pictoDigiQuali . $langs->trans('Controls') . ':digiquali@digiquali:$user->rights->digiquali->control->read:/custom/digiquali/view/control/control_list.php?fromid=__ID__&fromtype=' . $linkableElement['link_name']];
				$this->tabs[] = ['data' => $objectType . ':+survey:' . $pictoDigiQuali . $langs->trans('Surveys') . ':digiquali@digiquali:$user->rights->digiquali->survey->read:/custom/digiquali/view/survey/survey_list.php?fromid=__ID__&fromtype=' . $linkableElement['link_name']];

                $this->module_parts['hooks'][] = $linkableElement['hook_name_list'];
                $this->module_parts['hooks'][] = $linkableElement['hook_name_card'];
			}
		}

        // Dictionaries
        $this->dictionaries = [
            'langs' => 'digiquali@digiquali',
            // List of tables we want to see into dictonnary editor
            'tabname' => [
                MAIN_DB_PREFIX . 'c_question_type',
                MAIN_DB_PREFIX . 'c_control_attendants_role',
                MAIN_DB_PREFIX . 'c_survey_attendants_role',
            ],
            // Label of tables
            'tablib' => [
                'Question',
                'Control',
                'Survey'
            ],
            // Request to select fields
            'tabsql' => [
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.default_answers, f.position, f.active  FROM ' . MAIN_DB_PREFIX . 'c_question_type as f',
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.position, f.active FROM ' . MAIN_DB_PREFIX . 'c_control_attendants_role as f',
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.position, f.active FROM ' . MAIN_DB_PREFIX . 'c_survey_attendants_role as f'
            ],
            // Sort order
            'tabsqlsort' => [
                'label ASC',
                'label ASC',
                'label ASC'
            ],
            // List of fields (result of select to show dictionary)
            'tabfield' => [
                'ref,label,description,default_answers,position',
                'ref,label,description,position',
                'ref,label,description,position'
            ],
            // List of fields (list of fields to edit a record)
            'tabfieldvalue' => [
                'ref,label,description,default_answers,position',
                'ref,label,description,position',
                'ref,label,description,position'
            ],
            // List of fields (list of fields for insert)
            'tabfieldinsert' => [
                'ref,label,description,default_answers,position',
                'ref,label,description,position',
                'ref,label,description,position'
            ],
            // Name of columns with primary key (try to always name it 'rowid')
            'tabrowid' => [
                'rowid',
                'rowid',
                'rowid'
            ],
            // Condition to show each dictionary
            'tabcond' => [
                $conf->digiquali->enabled,
                $conf->digiquali->enabled,
                $conf->digiquali->enabled
            ]
        ];

		// Boxes/Widgets
		// Add here list of php file(s) stored in digiquali/core/boxes that contains a class to show a widget.
		$this->boxes = [];

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		$this->cronjobs = [];

		// Permissions provided by this module
		$this->rights = [];
		$r = 0;

		/* DIGIQUALI PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
		$this->rights[$r][1] = $langs->trans('LireModule', 'DigiQuali');
		$this->rights[$r][4] = 'lire';
		$this->rights[$r][5] = 1;
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
		$this->rights[$r][1] = $langs->trans('ReadModule', 'DigiQuali');
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = 1;
		$r++;

		/* CONTROL PERMISSSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('ReadObjects',$langs->transnoentities('ControlsMin')); // Permission label
		$this->rights[$r][4] = 'control'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('ControlsMin')); // Permission label
		$this->rights[$r][4] = 'control'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('ControlsMin')); // Permission label
		$this->rights[$r][4] = 'control'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('CanSetVerdict'); // Permission label
		$this->rights[$r][4] = 'control'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'setverdict'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;

		/* QUESTION PERMISSSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('ReadObjects',$langs->transnoentities('Questions')); // Permission label
		$this->rights[$r][4] = 'question'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('Questions')); // Permission label
		$this->rights[$r][4] = 'question'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('Questions')); // Permission label
		$this->rights[$r][4] = 'question'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;

		/* SHEET PERMISSSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('ReadObjects',$langs->transnoentities('Sheets')); // Permission label
		$this->rights[$r][4] = 'sheet'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('Sheets')); // Permission label
		$this->rights[$r][4] = 'sheet'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('Sheets')); // Permission label
		$this->rights[$r][4] = 'sheet'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
		$r++;

        /* SURVEY PERMISSSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->transnoentities('ReadObjects', dol_strtolower($langs->transnoentities('Surveys'))); // Permission label
        $this->rights[$r][4] = 'survey'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
        $this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->transnoentities('CreateObjects', dol_strtolower($langs->transnoentities('Surveys'))); // Permission label
        $this->rights[$r][4] = 'survey'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
        $this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->transnoentities('DeleteObjects', dol_strtolower($langs->transnoentities('Surveys'))); // Permission label
        $this->rights[$r][4] = 'survey'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
        $this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->digiquali->level1->level2)
        $r++;

		/* ADMINPAGE PANEL ACCESS PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
		$this->rights[$r][1] = $langs->transnoentities('ReadAdminPage', 'DigiQuali');
		$this->rights[$r][4] = 'adminpage';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
		$this->rights[$r][1] = $langs->transnoentities('ChangeUserController');
		$this->rights[$r][4] = 'adminpage';
		$this->rights[$r][5] = 'changeusercontroller';

		// Main menu entries to add
		$this->menu = [];
		$r = 0;

		// Add here entries to declare new menus
		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali',
			'type'     => 'top',
			'titre'    => $langs->trans('DigiQuali'),
			'prefix'   => '<i class="fas fa-home pictofixedwidth"></i>',
			'mainmenu' => 'digiquali',
			'leftmenu' => '',
			'url'      => '/digiquali/digiqualiindex.php',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $user->rights->digiquali->lire',
			'perms'    => '$user->rights->digiquali->lire',
			'target'   => '',
			'user'     => 0,
		];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali',
			'type'     => 'left',
			'titre'    => $langs->transnoentities('Question'),
			'prefix'   => '<i class="fas fa-question pictofixedwidth"></i>',
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_question',
			'url'      => '/digiquali/view/question/question_list.php',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $user->rights->digiquali->question->read',
			'perms'    => '$user->rights->digiquali->question->read',
			'target'   => '',
			'user'     => 0,
		];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali,fk_leftmenu=digiquali_question',
			'type'     => 'left',
			'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_questiontags',
			'url'      => '/categories/index.php?type=question',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $conf->categorie->enabled && $user->rights->digiquali->question->read',
			'perms'    => '$user->rights->digiquali->question->read',
			'target'   => '',
			'user'     => 0,
		];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali',
			'type'     => 'left',
			'titre'    => $langs->transnoentities('Sheet'),
			'prefix'   => '<i class="fas fa-list pictofixedwidth"></i>',
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_sheet',
			'url'      => '/digiquali/view/sheet/sheet_list.php',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $user->rights->digiquali->sheet->read',
			'perms'    => '$user->rights->digiquali->sheet->read',
			'target'   => '',
			'user'     => 0,
		];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali,fk_leftmenu=digiquali_sheet',
			'type'     => 'left',
			'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_sheettags',
			'url'      => '/categories/index.php?type=sheet',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $conf->categorie->enabled && $user->rights->digiquali->sheet->read',
			'perms'    => '$user->rights->digiquali->sheet->read',
			'target'   => '',
			'user'     => 0,
		];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali',
			'type'     => 'left',
			'titre'    => $langs->transnoentities('Control'),
			'prefix'   => '<i class="fas fa-tasks pictofixedwidth"></i>',
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_control',
			'url'      => '/digiquali/view/control/control_list.php',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $user->rights->digiquali->control->read',
			'perms'    => '$user->rights->digiquali->control->read',
			'target'   => '',
			'user'     => 0,
		];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali,fk_leftmenu=digiquali_control',
			'type'     => 'left',
			'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_controltags',
			'url'      => '/categories/index.php?type=control',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled && $conf->categorie->enabled && $user->rights->digiquali->control->read',
			'perms'    => '$user->rights->digiquali->control->read',
			'target'   => '',
			'user'     => 0,
		];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=digiquali',
            'type'     => 'left',
            'titre'    => $langs->transnoentities('Survey'),
            'prefix'   => '<i class="fas fa-marker pictofixedwidth"></i>',
            'mainmenu' => 'digiquali',
            'leftmenu' => 'digiquali_survey',
            'url'      => '/digiquali/view/survey/survey_list.php',
            'langs'    => 'digiquali@digiquali',
            'position' => 1000 + $r,
            'enabled'  => '$conf->digiquali->enabled && $user->rights->digiquali->survey->read',
            'perms'    => '$user->rights->digiquali->survey->read',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=digiquali,fk_leftmenu=digiquali_survey',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
            'mainmenu' => 'digiquali',
            'leftmenu' => 'digiquali_surveytags',
            'url'      => '/categories/index.php?type=survey',
            'langs'    => 'digiquali@digiquali',
            'position' => 1000 + $r,
            'enabled'  => '$conf->digiquali->enabled && $conf->categorie->enabled && $user->rights->digiquali->survey->read',
            'perms'    => '$user->rights->digiquali->survey->read',
            'target'   => '',
            'user'     => 0,
        ];

		$this->menu[$r++] = [
			'fk_menu'  => 'fk_mainmenu=digiquali',
			'type'     => 'left',
			'titre'    => '<i class="fas fa-wrench pictofixedwidth"></i>' . $langs->transnoentities('Tools'),
			'mainmenu' => 'digiquali',
			'leftmenu' => 'digiquali_tools',
			'url'      => '/digiquali/view/digiqualitools.php',
			'langs'    => 'digiquali@digiquali',
			'position' => 1000 + $r,
			'enabled'  => '$conf->digiquali->enabled',
			'perms'    => '$user->rights->digiquali->question->write && $user->rights->digiquali->sheet->write',
			'target'   => '',
			'user'     => 0,
		];
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = ''): int
	{
		global $conf, $langs, $user;

		if ($this->error > 0) {
			setEventMessages('', $this->errors, 'errors');
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		$sql    = [];
		$result = $this->_load_tables('/digiquali/sql/');

		// Load sql sub folders
		$sqlFolder = scandir(__DIR__ . '/../../sql');
		foreach ($sqlFolder as $subFolder) {
			if ( ! preg_match('/\./', $subFolder)) {
				$this->_load_tables('/digiquali/sql/' . $subFolder . '/');
			}
		}

		if (getDolGlobalInt('DIGIQUALI_DOCUMENT_DIRECTORIES_NAME_BACKWARD_COMPATIBILITY') == 0) {
			$documentsPath = DOL_DATA_ROOT . ($conf->entity > 1 ? '/' . $conf->entity : '');
			$ecmPath =  $documentsPath . '/ecm' ;

			if (is_dir($ecmPath)) {
				if (is_dir($ecmPath . '/dolismq')) {
                    chmod($ecmPath . '/dolismq', 0755);
                    rename($ecmPath . '/dolismq', $ecmPath . '/digiquali');
				}
			}

			$moduleDocumentsPath = $documentsPath . '/dolismq';
			if (is_dir($moduleDocumentsPath)) {
                chmod($moduleDocumentsPath, 0755);
				rename($moduleDocumentsPath, $documentsPath . '/digiquali');
			}

			dolibarr_set_const($this->db, 'DIGIQUALI_DOCUMENT_DIRECTORIES_NAME_BACKWARD_COMPATIBILITY', $this->version, 'integer', 1, '', $conf->entity);
		}


		dolibarr_set_const($this->db, 'DIGIQUALI_VERSION', $this->version, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($this->db, 'DIGIQUALI_DB_VERSION', $this->version, 'chaine', 0, '', $conf->entity);

		delDocumentModel('controldocument_odt', 'controldocument');
        delDocumentModel('surveydocument_odt', 'surveydocument');
		delDocumentModel('calypso_controldocument', 'controldocument');

		addDocumentModel('controldocument_odt', 'controldocument', 'ODT templates', 'DIGIQUALI_CONTROLDOCUMENT_ADDON_ODT_PATH');
		addDocumentModel('surveydocument_odt', 'surveydocument', 'ODT templates', 'DIGIQUALI_SURVEYDOCUMENT_ADDON_ODT_PATH');

		if (!empty($conf->global->DIGIQUALI_SHEET_TAGS_SET) && empty($conf->global->DIGIQUALI_SHEET_DEFAULT_TAG)) {
			global $user, $langs;
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

			$tags = new Categorie($this->db);
			$tags->label = $langs->transnoentities('Default');
			$tags->type  = 'sheet';
			$tags->create($user);

			dolibarr_set_const($this->db, 'DIGIQUALI_SHEET_DEFAULT_TAG', $tags->id, 'integer', 0, '', $conf->entity);
		}
        // Create extrafields during init.
        include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extraFields = new ExtraFields($this->db);
		$linkableElements = get_sheet_linkable_objects();

		if (is_array($linkableElements) && !empty($linkableElements)) {
			foreach($linkableElements as $linkableElementType => $linkableElement) {
				$className      = $linkableElement['className'];
				$linkableObject = new $className($this->db);
				$tableElement   = $linkableObject->table_element;

                $extraFields->addExtraField('qc_frequency', 'QcFrequency', 'int', 100, 10, $tableElement, 0, 0, '', 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, '', 1, '','',0, 'digiquali@digiquali', '$conf->digiquali->enabled');

                $extraFields->update('control_history_link', 'ControlHistoryLink', 'varchar', 255, $tableElement, 0, 0, 110, '', 0, '', 5, '', '', '', 0, 'digiquali@digiquali', '$conf->digiquali->enabled');
                $extraFields->addExtraField('control_history_link', 'ControlHistoryLink', 'varchar', 110, 255, $tableElement, 0, 0, '', '', 0, '', 5, '','',0, 'digiquali@digiquali', '$conf->digiquali->enabled');
			}
		}

		if ($result < 0) {
			return -1;
		} // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')

    if (getDolGlobalInt('DIGIQUALI_CONTROL_BACKWARD_COMPATIBILITY') == 0) {
        require_once TCPDF_PATH . 'tcpdf_barcodes_2d.php';
        require_once __DIR__ . '/../../class/control.class.php';
        $control  = new Control($this->db);
        $controls = $control->fetchAll();
        if (is_array($controls) && !empty($controls)) {
            foreach ($controls as $control) {
                $control->track_id = generate_random_id();
                $control->update($user, true);

                $url = dol_buildpath('custom/digiquali/public/control/public_control.php?track_id=' . $control->track_id . '&entity=' . $conf->entity, 3);

                $barcode = new TCPDF2DBarcode($url, 'QRCODE,L');
                dol_mkdir(DOL_DATA_ROOT . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'digiquali/control/' . $control->ref . '/qrcode/');
                $file = DOL_DATA_ROOT . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'digiquali/control/' . $control->ref . '/qrcode/barcode_' . $control->track_id . '.png';

                $imageData = $barcode->getBarcodePngData();
                $imageData = imagecreatefromstring($imageData);
                imagepng($imageData, $file);
            }
        }

        dolibarr_set_const($this->db, 'DIGIQUALI_CONTROL_BACKWARD_COMPATIBILITY', 1, 'integer', 0, '', $conf->entity);
    }

        if (getDolGlobalInt('DIGIQUALI_SHEET_BACKWARD_COMPATIBILITY') == 0) {
            require_once __DIR__ . '/../../class/sheet.class.php';
            $sheet  = new Sheet($this->db);
            $sheets = $sheet->fetchAll();
            if (is_array($sheets) && !empty($sheets)) {
                foreach ($sheets as $sheet) {
                    $sheet->type = 'control';
                    $sheet->setValueFrom('type', $sheet->type, '', '', 'text', '', $user, strtoupper($sheet->element) . '_MODIFY');
                }
            }

            dolibarr_set_const($this->db, 'DIGIQUALI_SHEET_BACKWARD_COMPATIBILITY', 1, 'integer', 0, '', $conf->entity);
        }

		// Permissions
		$this->remove($options);

		$result = $this->_init($sql, $options);

		if (getDolGlobalInt('DIGIQUALI_QUESTION_BACKWARD_COMPATIBILITY') == 0 && $result > 0) {
			require_once __DIR__ . '/../../class/question.class.php';
			require_once __DIR__ . '/../../class/answer.class.php';

			$question  = new Question($this->db);
			$answer    = new Answer($this->db);

			$questions = $question->fetchAll('', '', 0, 0, ['customsql' => 't.type = "OkKoToFixNonApplicable"']);
			if (is_array($questions) && !empty($questions)) {
				foreach ($questions as $question) {
					$answer->fk_question = $question->id;
					$answer->value       = $langs->transnoentities('OK');
					$answer->pictogram   = 'check';
					$answer->color       = '#47e58e';

					$answer->create($user);

					$answer->fk_question = $question->id;
					$answer->value       = $langs->transnoentities('KO');
					$answer->pictogram   = 'times';
					$answer->color       = '#e05353';

					$answer->create($user);

					$answer->fk_question = $question->id;
					$answer->value       = $langs->transnoentities('ToFix');
					$answer->pictogram   = 'tools';
					$answer->color       = '#e9ad4f';

					$answer->create($user);

					$answer->fk_question = $question->id;
					$answer->value       = $langs->transnoentities('NonApplicable');
					$answer->pictogram   = 'N/A';
					$answer->color       = '#2b2b2b';

					$answer->create($user);
				}
			}

			dolibarr_set_const($this->db, 'DIGIQUALI_QUESTION_BACKWARD_COMPATIBILITY', 1, 'integer', 0, '', $conf->entity);
		}

        require_once DOL_DOCUMENT_ROOT . '/cron/class/cronjob.class.php';

        $cronJob = new Cronjob($this->db);
        $cronJob->fetch(0, 'ActionComm', 'sendEmailsReminder');
        $cronJob->reprogram_jobs($user->login, dol_now());

		 return $result;
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = ''): int
	{
		$sql = [];
		return $this->_remove($sql, $options);
	}
}
