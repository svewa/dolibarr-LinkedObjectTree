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
 * \file       class/linkedobjecttree.class.php
 * \ingroup    linkedobjecttree
 * \brief      Class to build and display linked objects tree
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class LinkedObjectTree
 *
 * Handles building and displaying complete tree of linked objects
 */
class LinkedObjectTree
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var array Tree structure
	 */
	public $tree = array();

	/**
	 * @var array Visited nodes to prevent infinite loops
	 */
	private $visited = array();

	/**
	 * @var int Maximum depth
	 */
	private $maxDepth = 10;

	/**
	 * @var int Current object ID
	 */
	private $currentObjectId;

	/**
	 * @var string Current object type
	 */
	private $currentObjectType;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		
		if (!empty($conf->global->LINKEDOBJECTTREE_MAX_DEPTH)) {
			$this->maxDepth = (int) $conf->global->LINKEDOBJECTTREE_MAX_DEPTH;
		}
	}

	/**
	 * Build complete tree for an object
	 *
	 * @param CommonObject $object Object to build tree for
	 * @return array Tree structure
	 */
	public function buildCompleteTree($object)
	{
		$this->currentObjectId = $object->id;
		$this->currentObjectType = $object->element;
		$this->visited = array();
		$this->tree = array();

		dol_syslog("LinkedObjectTree: buildCompleteTree for ".$object->element." ID ".$object->id, LOG_DEBUG);

		// Find root(s) of the tree
		$roots = $this->findRoots($object->id, $object->element);

		dol_syslog("LinkedObjectTree: Found ".count($roots)." root(s)", LOG_DEBUG);

		// Build tree from each root
		$treeNodes = array();
		foreach ($roots as $root) {
			dol_syslog("LinkedObjectTree: Building tree from root ".$root['type']." ID ".$root['id'], LOG_DEBUG);
			$this->visited = array(); // Reset visited for each root
			$node = $this->buildTreeFromNode($root['id'], $root['type'], 0);
			if ($node !== null) {
				$treeNodes[] = $node;
			} else {
				dol_syslog("LinkedObjectTree: buildTreeFromNode returned null for root", LOG_WARNING);
			}
		}

		dol_syslog("LinkedObjectTree: Built tree with ".count($treeNodes)." nodes", LOG_DEBUG);

		return $treeNodes;
	}

	/**
	 * Find root objects (objects with no parents)
	 *
	 * @param int $objectId Object ID
	 * @param string $objectType Object type
	 * @return array Array of root objects
	 */
	private function findRoots($objectId, $objectType)
	{
		$roots = array();
		$visited = array();
		$toCheck = array(array('id' => $objectId, 'type' => $objectType));

		while (!empty($toCheck)) {
			$current = array_shift($toCheck);
			$key = $current['type'].'_'.$current['id'];

			if (isset($visited[$key])) {
				continue;
			}
			$visited[$key] = true;

			// Get parents
			$parents = $this->getParents($current['id'], $current['type']);

			if (empty($parents)) {
				// No parents, this is a root
				$roots[$key] = $current;
			} else {
				// Add parents to check
				foreach ($parents as $parent) {
					$toCheck[] = $parent;
				}
			}
		}

		return array_values($roots);
	}

	/**
	 * Get parent objects
	 *
	 * @param int $objectId Object ID
	 * @param string $objectType Object type
	 * @return array Array of parent objects
	 */
	private function getParents($objectId, $objectType)
	{
		$parents = array();

		$sql = "SELECT fk_source, sourcetype";
		$sql .= " FROM ".MAIN_DB_PREFIX."element_element";
		$sql .= " WHERE fk_target = ".((int) $objectId);
		$sql .= " AND targettype = '".$this->db->escape($objectType)."'";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$parents[] = array(
					'id' => $obj->fk_source,
					'type' => $obj->sourcetype
				);
			}
		}

		return $parents;
	}

	/**
	 * Get child objects
	 *
	 * @param int $objectId Object ID
	 * @param string $objectType Object type
	 * @return array Array of child objects
	 */
	private function getChildren($objectId, $objectType)
	{
		$children = array();

		$sql = "SELECT fk_target, targettype";
		$sql .= " FROM ".MAIN_DB_PREFIX."element_element";
		$sql .= " WHERE fk_source = ".((int) $objectId);
		$sql .= " AND sourcetype = '".$this->db->escape($objectType)."'";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$children[] = array(
					'id' => $obj->fk_target,
					'type' => $obj->targettype
				);
			}
		}

		return $children;
	}

	/**
	 * Build tree recursively from a node
	 *
	 * @param int $objectId Object ID
	 * @param string $objectType Object type
	 * @param int $depth Current depth
	 * @return array|null Tree node or null if max depth reached
	 */
	private function buildTreeFromNode($objectId, $objectType, $depth)
	{
		dol_syslog("LinkedObjectTree: buildTreeFromNode type=".$objectType." id=".$objectId." depth=".$depth, LOG_DEBUG);
		
		// Check depth limit
		if ($depth > $this->maxDepth) {
			dol_syslog("LinkedObjectTree: Max depth reached", LOG_DEBUG);
			return null;
		}

		$key = $objectType.'_'.$objectId;

		// Check if already visited (circular reference)
		if (isset($this->visited[$key])) {
			dol_syslog("LinkedObjectTree: Already visited ".$key, LOG_DEBUG);
			return null;
		}
		$this->visited[$key] = true;

		// Load object details
		$objectData = $this->loadObjectData($objectId, $objectType);
		if (!$objectData) {
			dol_syslog("LinkedObjectTree: loadObjectData returned false/empty for ".$key, LOG_WARNING);
			return null;
		}

		dol_syslog("LinkedObjectTree: Successfully loaded object data for ".$key, LOG_DEBUG);

		// Build node
		$node = array(
			'id' => $objectId,
			'type' => $objectType,
			'data' => $objectData,
			'is_current' => ($objectId == $this->currentObjectId && $objectType == $this->currentObjectType),
			'children' => array(),
			'depth' => $depth
		);

		// Get children
		$children = $this->getChildren($objectId, $objectType);
		dol_syslog("LinkedObjectTree: Found ".count($children)." children for ".$key, LOG_DEBUG);
		
		foreach ($children as $child) {
			$childNode = $this->buildTreeFromNode($child['id'], $child['type'], $depth + 1);
			if ($childNode !== null) {
				$node['children'][] = $childNode;
			}
		}

		return $node;
	}

	/**
	 * Load object data using Dolibarr's native getElementProperties
	 *
	 * @param int $objectId Object ID
	 * @param string $objectType Object type
	 * @return array|false Object data or false on error
	 */
	private function loadObjectData($objectId, $objectType)
	{
		global $langs;

		// Use Dolibarr's native method to get element properties
		$tmpobject = new CommonObject($this->db);
		$elementProperties = $tmpobject->getElementProperties($objectType);
		
		dol_syslog("LinkedObjectTree: Loading object type=".$objectType." id=".$objectId, LOG_DEBUG);
		
		if (empty($elementProperties)) {
			dol_syslog("LinkedObjectTree: getElementProperties returned empty for type ".$objectType, LOG_WARNING);
			// Fallback for unknown types
			return array(
				'ref' => '?',
				'label' => $objectType.' #'.$objectId,
				'url' => '',
				'status' => '',
			);
		}
		
		dol_syslog("LinkedObjectTree: Element properties: ".print_r($elementProperties, true), LOG_DEBUG);
		
		if (empty($elementProperties['classfile']) || empty($elementProperties['classpath'])) {
			dol_syslog("LinkedObjectTree: Missing classfile or classpath for type ".$objectType, LOG_WARNING);
			// Fallback for unknown types
			return array(
				'ref' => '?',
				'label' => $objectType.' #'.$objectId,
				'url' => '',
				'status' => '',
			);
		}

		// Extract class information from element properties
		$classname = $elementProperties['classname'];
		$classpath = $elementProperties['classpath'];
		$classfile = $elementProperties['classfile'];

		// Load the class file and instantiate the object (add .class.php extension like Dolibarr does)
		$filepath = DOL_DOCUMENT_ROOT.'/'.$classpath.'/'.$classfile.'.class.php';

		dol_syslog("LinkedObjectTree: Trying to load file: ".$filepath, LOG_DEBUG);

		if (!file_exists($filepath)) {
			dol_syslog("LinkedObjectTree: File not found: ".$filepath, LOG_ERR);
			return false;
		}

		if (!class_exists($classname)) {
			require_once $filepath;
		}

		$obj = new $classname($this->db);
		$result = $obj->fetch($objectId);

		if ($result > 0) {
			// Determine the appropriate date field based on object type (matching Dolibarr's original behavior)
			$date = '';
			
			// Map object types to their specific date fields (based on Dolibarr's linkedobjectblock templates)
			switch ($objectType) {
				case 'expedition':
				case 'shipping':
					// Shipments: date_delivery with fallback to date_creation
					$date = !empty($obj->date_delivery) ? $obj->date_delivery : (!empty($obj->date_creation) ? $obj->date_creation : '');
					break;
					
				case 'reception':
				case 'delivery':
					// Receptions and deliveries: date_delivery
					$date = !empty($obj->date_delivery) ? $obj->date_delivery : '';
					break;
					
				case 'contrat':
				case 'contract':
					// Contracts: date_contrat
					$date = !empty($obj->date_contrat) ? $obj->date_contrat : '';
					break;
					
				case 'ficheinter':
				case 'intervention':
					// Interventions: datev (date of intervention)
					$date = !empty($obj->datev) ? $obj->datev : '';
					break;
					
				case 'expensereport':
					// Expense reports: date_debut (start date)
					$date = !empty($obj->date_debut) ? $obj->date_debut : '';
					break;
					
				case 'subscription':
					// Subscriptions: dateh
					$date = !empty($obj->dateh) ? $obj->dateh : '';
					break;
					
				case 'asset':
					// Assets: date_start
					$date = !empty($obj->date_start) ? $obj->date_start : '';
					break;
					
				case 'bom':
				case 'mrp':
				case 'mo':
				case 'supplier_proposal':
					// BOM, MRP, MO, Supplier proposals: date_creation
					$date = !empty($obj->date_creation) ? $obj->date_creation : '';
					break;
					
				case 'ticket':
					// Tickets: datec (creation date)
					$date = !empty($obj->datec) ? $obj->datec : '';
					break;
					
				case 'conferenceorbooth':
				case 'conferenceorboothattendee':
					// Event organization: date_subscription
					$date = !empty($obj->date_subscription) ? $obj->date_subscription : '';
					break;
					
				default:
					// Default: try 'date' field first (used by most objects like invoices, orders, proposals)
					// then fall back to 'datec' (creation date) if 'date' is not available
					$date = !empty($obj->date) ? $obj->date : (!empty($obj->datec) ? $obj->datec : '');
					break;
			}
			
			return array(
				'ref' => $obj->ref,
				'label' => $obj->ref.(isset($obj->label) ? ' - '.$obj->label : ''),
				'url' => $obj->getNomUrl(1),
				'status' => method_exists($obj, 'getLibStatut') ? $obj->getLibStatut(3) : '',
				'date' => $date,
				'amount' => isset($obj->total_ttc) ? $obj->total_ttc : (isset($obj->total_ht) ? $obj->total_ht : ''),
			);
		}
		
		dol_syslog("LinkedObjectTree: Failed to fetch object id=".$objectId." type=".$objectType." result=".$result, LOG_WARNING);

		return false;
	}

	/**
	 * Render tree as HTML
	 *
	 * @param array $treeNodes Array of tree nodes
	 * @param CommonObject $object Current object (needed for link dropdown)
	 * @return string HTML output
	 */
	public function renderTreeHTML($treeNodes, $object = null)
	{
		global $langs, $conf, $user;

		if (empty($treeNodes)) {
			return '<div class="linkedobjecttree-empty">'.$langs->trans("NoLinkedObjects").'</div>';
		}

		// Build "Link to object" dropdown using Dolibarr's native function
		$linktoelem = '';
		$htmltoenteralink = '';
		
		if (!empty($object)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
			$form = new Form($this->db);
			
			// Get the element type to exclude (don't allow linking to same type)
			$excludeType = array($object->element);
			
			// Call Dolibarr's native function to generate the link dropdown
			// Returns array with 'linktoelem' (the dropdown) and 'htmltoenteralink' (the forms)
			$tmparray = $form->showLinkToObjectBlock($object, array(), $excludeType, 1);
			
			// Separate the dropdown from the forms
			if (!empty($tmparray['linktoelem'])) {
				$linktoelem = $tmparray['linktoelem'];
			}
			
			if (!empty($tmparray['htmltoenteralink'])) {
				$htmltoenteralink = $tmparray['htmltoenteralink'];
			}
		}

		// Add proper Dolibarr section header with link action (only the dropdown)
		$html = '<br>'."\n";
		$html .= '<div class="div-table-responsive-no-min">'."\n";
		
		$html .= load_fiche_titre($langs->trans("RelatedObjects"), $linktoelem, '');
		
		// Print the linking forms AFTER the title but BEFORE the table
		// This is where users select which invoice/order to link
		$html .= $htmltoenteralink;
		
		// Add the JavaScript to handle linkto clicks (normally printed by showLinkToObjectBlock when nooutput=0)
		// We need to add it manually since we're using nooutput=1
		if (!empty($linktoelem) && !empty($conf->use_javascript_ajax)) {
			$html .= '<!-- Add js to show linkto box -->
				<script nonce="' . getNonce() . '">
				jQuery(document).ready(function() {
					jQuery(".linkto").click(function(e) {
						console.log("We choose to show/hide links for rel="+jQuery(this).attr(\'rel\')+" so #"+jQuery(this).attr(\'rel\')+"list");
						jQuery("#"+jQuery(this).attr(\'rel\')+"list").toggle();
						e.stopPropagation(); // Prevent dropdown from closing
						return false;
					});
				});
				</script>
			';
		}
		
		// Render as table with columns
		$html .= '<table class="noborder linkedobjecttree-table centpercent">'."\n";
		
		// Table header
		$html .= '<tr class="liste_titre">';
		$html .= '<th class="linkedobjecttree-col-ref">'.$langs->trans("Ref").'</th>';
		$html .= '<th class="linkedobjecttree-col-type">'.$langs->trans("Type").'</th>';
		$html .= '<th class="linkedobjecttree-col-date">'.$langs->trans("Date").'</th>';
		$html .= '<th class="linkedobjecttree-col-amount right">'.$langs->trans("AmountHT").'</th>';
		$html .= '<th class="linkedobjecttree-col-status right">'.$langs->trans("Status").'</th>';
		$html .= '<th class="linkedobjecttree-col-action right"></th>';
		$html .= '</tr>'."\n";

		// Table body
		foreach ($treeNodes as $node) {
			$html .= $this->renderNodeHTML($node);
		}

		$html .= '</table>'."\n";
		$html .= '</div>'."\n";

		return $html;
	}

	/**
	 * Render single node as HTML
	 *
	 * @param array $node Node data
	 * @return string HTML output
	 */
	private function renderNodeHTML($node)
	{
		global $langs;
		
		$rowClass = 'oddeven linkedobjecttree-row';
		if ($node['is_current']) {
			$rowClass .= ' linkedobjecttree-current';
		}
		
		$hasChildren = !empty($node['children']);
		if ($hasChildren) {
			$rowClass .= ' has-children';
		}
		
		$html = '<tr class="'.$rowClass.'" data-depth="'.$node['depth'].'">';
		
		// Column 1: Ref with tree structure
		$html .= '<td class="linkedobjecttree-col-ref">';
		
		// Indentation for tree structure
		if ($node['depth'] > 0) {
			$html .= '<span class="linkedobjecttree-indent" style="margin-left: '.($node['depth'] * 20).'px;">';
		}
		
		// Toggle button for nodes with children
		if ($hasChildren) {
			$html .= '<span class="linkedobjecttree-toggle-btn" title="'.$langs->trans("ExpandCollapse").'">';
			$html .= '<span class="fa fa-minus-square"></span>';
			$html .= '</span> ';
		} else {
			// Empty space for alignment
			$html .= '<span class="linkedobjecttree-toggle-spacer"></span> ';
		}
		
		// Object link - getNomUrl() already includes the icon
		if ($node['is_current']) {
			$html .= '<strong>'.$node['data']['url'].'</strong>';
		} else {
			$html .= $node['data']['url'];
		}
		
		if ($node['depth'] > 0) {
			$html .= '</span>';
		}
		
		$html .= '</td>';
		
		// Column 2: Type
		$html .= '<td class="linkedobjecttree-col-type">';
		$html .= $this->getTranslatedType($node['type']);
		$html .= '</td>';
		
		// Column 3: Date
		$html .= '<td class="linkedobjecttree-col-date">';
		if (!empty($node['data']['date'])) {
			$html .= dol_print_date($node['data']['date'], 'day');
		}
		$html .= '</td>';
		
		// Column 4: Amount
		$html .= '<td class="linkedobjecttree-col-amount right">';
		if (isset($node['data']['amount']) && $node['data']['amount'] !== '') {
			$html .= price($node['data']['amount']);
		}
		$html .= '</td>';
		
		// Column 5: Status
		$html .= '<td class="linkedobjecttree-col-status right">';
		if (!empty($node['data']['status'])) {
			$html .= $node['data']['status'];
		}
		$html .= '</td>';
		
		// Column 6: Action (unlink button)
		$html .= '<td class="linkedobjecttree-col-action right">';
		
		// Determine if unlinking is allowed based on Dolibarr's business rules
		$canUnlink = $this->canUnlinkObject($node['type'], $this->currentObjectType);
		
		if ($canUnlink && !$node['is_current']) {
			// Build unlink URL
			$unlinkUrl = $_SERVER["PHP_SELF"].'?id='.$this->currentObjectId.'&action=dellink&token='.newToken();
			$unlinkUrl .= '&dellinkid='.$node['id'].'&dellinktype='.urlencode($node['type']);
			
			$html .= '<a class="reposition" href="'.$unlinkUrl.'" title="'.$langs->transnoentitiesnoconv("RemoveLink").'">';
			$html .= img_picto($langs->transnoentitiesnoconv("RemoveLink"), 'unlink');
			$html .= '</a>';
		}
		
		$html .= '</td>';
		
		$html .= '</tr>';

		// Render children
		if ($hasChildren) {
			foreach ($node['children'] as $child) {
				$html .= $this->renderNodeHTML($child);
			}
		}

		return $html;
	}

	/**
	 * Get translated name for object type using Dolibarr's native translation
	 *
	 * @param string $type Object type
	 * @return string Translated type name
	 */
	private function getTranslatedType($type)
	{
		global $langs;
		
		// Use Dolibarr's native method to get element properties
		$tmpobject = new CommonObject($this->db);
		$elementProperties = $tmpobject->getElementProperties($type);
		
		if (!empty($elementProperties) && !empty($elementProperties['langs'])) {
			// Load the language file for this element
			$langs->load($elementProperties['langs']);
			
			// Use the lang key if available, otherwise use a default translation
			$langkey = !empty($elementProperties['langfile']) ? $elementProperties['langfile'] : ucfirst($type);
			
			// Try to get the module-specific translation, fallback to ucfirst
			$translated = $langs->trans($langkey);
			if ($translated != $langkey) {
				return $translated;
			}
		}
		
		// Fallback: try common translations
		$commonTranslations = array(
			'facture' => 'Invoice',
			'propal' => 'Proposal',
			'commande' => 'Order',
			'expedition' => 'Shipment',
			'contrat' => 'Contract',
		);
		
		$translationKey = isset($commonTranslations[$type]) ? $commonTranslations[$type] : ucfirst($type);
		return $langs->trans($translationKey);
	}


	/**
	 * Check if an object can be unlinked based on Dolibarr's business rules
	 *
	 * @param string $linkedObjectType Type of the linked object to unlink
	 * @param string $currentObjectType Type of the current object
	 * @return bool True if unlinking is allowed
	 */
	private function canUnlinkObject($linkedObjectType, $currentObjectType)
	{
		// Normalize object types (handle variations like 'shipping' vs 'expedition')
		$normalizedLinked = $this->normalizeObjectType($linkedObjectType);
		$normalizedCurrent = $this->normalizeObjectType($currentObjectType);
		
		// Business rule 1: Shipments MUST stay linked to orders
		// If the current object is an order (commande), don't allow unlinking shipments
		if ($normalizedCurrent == 'commande' && $normalizedLinked == 'expedition') {
			return false;
		}
		
		// Business rule 2: Shipments MUST stay linked to orders (reverse direction)
		// If the current object is a shipment, don't allow unlinking orders
		if ($normalizedCurrent == 'expedition' && $normalizedLinked == 'commande') {
			return false;
		}
		
		// All other relationships can be unlinked
		return true;
	}

	/**
	 * Normalize object type names using getElementProperties
	 *
	 * @param string $type Object type
	 * @param string $returnField Field to return from properties (default: element)
	 * @return string Normalized type or original if not found
	 */
	private function normalizeObjectType($type, $returnField = 'element')
	{
		// Use Dolibarr's native method to get element properties
		$tmpobject = new CommonObject($this->db);
		$elementProperties = $tmpobject->getElementProperties($type);
		
		if (!empty($elementProperties) && !empty($elementProperties[$returnField])) {
			return $elementProperties[$returnField];
		}
		
		// Fallback to original type if not found
		return $type;
	}
}
