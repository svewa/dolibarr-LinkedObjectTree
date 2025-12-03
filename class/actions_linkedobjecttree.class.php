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
 */

/**
 * \file       class/actions_linkedobjecttree.class.php
 * \ingroup    linkedobjecttree
 * \brief      Hook overrides for LinkedObjectTree module
 */

dol_include_once('/linkedobjecttree/class/linkedobjecttree.class.php');

/**
 * Class ActionsLinkedObjectTree
 *
 * Hooks implementation to inject tree view into object cards
 */
class ActionsLinkedObjectTree
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var array Hook results
	 */
	public $results = array();

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var array Context
	 */
	public $context = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook to handle actions before page rendering
	 *
	 * @param array $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=OK
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		// Check if module is enabled
		if (empty($conf->linkedobjecttree->enabled)) {
			return 0;
		}

		// Handle unlink action
		if ($action == 'dellink' && !empty($object) && !empty($object->id)) {
			// Check token
			if (!newToken('check')) {
				return 0;
			}

			$dellinkid = GETPOST('dellinkid', 'int');
			$dellinktype = GETPOST('dellinktype', 'alpha');

			if ($dellinkid > 0 && !empty($dellinktype)) {
				dol_syslog("LinkedObjectTree: Unlinking ".$dellinktype." ID ".$dellinkid." from ".$object->element." ID ".$object->id, LOG_DEBUG);

				// Use Dolibarr's native method to delete the link
				$result = $object->deleteObjectLinked(null, '', $dellinkid, $dellinktype);

				if ($result > 0) {
					setEventMessages($langs->trans("LinkRemoved"), null, 'mesgs');
					// Redirect to avoid resubmission
					header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
					exit;
				} else {
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
		}

		return 0;
	}

	/**
	 * Hook called to replace the linked objects section
	 * This is the ONLY hook we use to avoid duplicate displays
	 *
	 * @param array $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=OK, 1=replace content
	 */
	public function showLinkedObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;

		// Debug: Log that hook was called
		dol_syslog("LinkedObjectTree: showLinkedObjectBlock called for ".get_class($object)." ID: ".$object->id, LOG_DEBUG);

		// Check if module is enabled
		if (empty($conf->linkedobjecttree->enabled)) {
			dol_syslog("LinkedObjectTree: Module not enabled", LOG_DEBUG);
			return 0;
		}

		// Check if object is valid and has an ID
		if (empty($object) || empty($object->id)) {
			dol_syslog("LinkedObjectTree: No valid object", LOG_DEBUG);
			return 0;
		}

		// Build and display the tree
		try {
			$tree = new LinkedObjectTree($this->db);
			$treeNodes = $tree->buildCompleteTree($object);
			
			dol_syslog("LinkedObjectTree: Built tree with ".count($treeNodes)." root nodes", LOG_DEBUG);
			
			if (!empty($treeNodes)) {
				// Pass the object to renderTreeHTML so it can generate the native link dropdown
				$html = $tree->renderTreeHTML($treeNodes, $object);
				
				dol_syslog("LinkedObjectTree: Generated HTML length: ".strlen($html), LOG_DEBUG);
				
				// PRINT the HTML directly - Dolibarr expects hooks to print, not return
				print $html;
				
				return 1; // Return 1 to replace default content
			} else {
				dol_syslog("LinkedObjectTree: No tree nodes found", LOG_DEBUG);
			}
		} catch (Exception $e) {
			$this->error = $e->getMessage();
			dol_syslog("LinkedObjectTree Error: ".$e->getMessage(), LOG_ERR);
			// Show error to user
			$this->resprints = '<div class="error">LinkedObjectTree Error: '.$e->getMessage().'</div>';
			return 1;
		}

		return 0;
	}
}
