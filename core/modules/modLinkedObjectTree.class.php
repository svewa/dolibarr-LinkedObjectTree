<?php
/* Copyright (C) 2024 Linked Object Tree Module
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
 * \defgroup   linkedobjecttree     Module LinkedObjectTree
 * \brief      LinkedObjectTree module descriptor.
 *
 * \file       htdocs/custom/linkedobjecttree/core/modules/modLinkedObjectTree.class.php
 * \ingroup    linkedobjecttree
 * \brief      Description and activation file for module LinkedObjectTree
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module LinkedObjectTree
 */
class modLinkedObjectTree extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique).
		$this->numero = 500000;
		
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'linkedobjecttree';

		// Family can be 'base', 'crm', 'financial', 'hr', 'projects', 'products', 'ecm', 'technic' (transverse modules), 'interface' (link with external tools), 'other', ...
		$this->family = "technic";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleLinkedObjectTreeName' not found (LinkedObjectTree is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleLinkedObjectTreeDesc' not found (LinkedObjectTree is name of module).
		$this->description = "Display linked objects in a complete tree view";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "Display linked objects in a complete tree view showing the full hierarchy of related documents (invoices, orders, shipments, etc.) with the current object highlighted in context.";

		// Author
		$this->editor_name = 'Custom Module';
		$this->editor_url = '';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0';

		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where LINKEDOBJECTTREE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		$this->picto = 'generic';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array('/linkedobjecttree/css/linkedobjecttree.css'),
			'js' => array('/linkedobjecttree/js/linkedobjecttree.js'),
			'hooks' => array(
				'data' => array(
					'invoicecard',
					'ordercard',
					'propalcard',
					'expeditioncard',
					'deliverycard',
					'supplierproposalcard',
					'supplierordercard',
					'supplierinvoicecard',
					'contractcard',
					'interventioncard',
					'ticketcard',
					'projectcard',
					'taskcard',
					'mrpcard',
					'bomcard',
				),
				'entity' => '0'
			),
		);

		// Data directories to create when module is enabled.
		$this->dirs = array();

		// Config pages. Put here list of php page, stored into linkedobjecttree/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@linkedobjecttree");

		// Dependencies
		$this->hidden = false; // A condition to hide module
		$this->depends = array(); // List of module class names as string that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR'...))
		$this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = array("linkedobjecttree@linkedobjecttree");

		// Prerequisites
		$this->phpmin = array(7, 0); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(11, 0); // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'LinkedObjectTreeWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		$this->const = array(
			1 => array(
				'LINKEDOBJECTTREE_MAX_DEPTH',
				'chaine',
				'10',
				'Maximum depth for tree traversal',
				0,
				'current',
				1
			),
		);

		// Array to add new pages in new tabs
		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes/Widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array();

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		// Main menu entries to add
		$this->menu = array();
	}

	/**
	 * Function called when module is enabled.
	 * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 * It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/linkedobjecttree/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extrafields during init
		//include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		//$extrafields = new ExtraFields($this->db);
		//$result1=$extrafields->addExtraField('linkedobjecttree_myattr1', "New Attr 1 label", 'boolean', 1,  3, 'thirdparty',   0, 0, '', '', 1, '', 0, 0, '', '', 'linkedobjecttree@linkedobjecttree', '$conf->linkedobjecttree->enabled');
		//$result2=$extrafields->addExtraField('linkedobjecttree_myattr2', "New Attr 2 label", 'varchar', 1, 10, 'project',      0, 0, '', '', 1, '', 0, 0, '', '', 'linkedobjecttree@linkedobjecttree', '$conf->linkedobjecttree->enabled');
		//$result3=$extrafields->addExtraField('linkedobjecttree_myattr3', "New Attr 3 label", 'varchar', 1, 10, 'bank_account', 0, 0, '', '', 1, '', 0, 0, '', '', 'linkedobjecttree@linkedobjecttree', '$conf->linkedobjecttree->enabled');
		//$result4=$extrafields->addExtraField('linkedobjecttree_myattr4', "New Attr 4 label", 'select',  1,  3, 'thirdparty',   0, 1, '', array('options'=>array('code1'=>'Val1','code2'=>'Val2','code3'=>'Val3')), 1,'', 0, 0, '', '', 'linkedobjecttree@linkedobjecttree', '$conf->linkedobjecttree->enabled');
		//$result5=$extrafields->addExtraField('linkedobjecttree_myattr5', "New Attr 5 label", 'text',    1, 10, 'user',         0, 0, '', '', 1, '', 0, 0, '', '', 'linkedobjecttree@linkedobjecttree', '$conf->linkedobjecttree->enabled');

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
