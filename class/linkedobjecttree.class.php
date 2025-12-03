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

		// Find root(s) of the tree
		$roots = $this->findRoots($object->id, $object->element);

		// Build tree from each root
		$treeNodes = array();
		foreach ($roots as $root) {
			$this->visited = array(); // Reset visited for each root
			$treeNodes[] = $this->buildTreeFromNode($root['id'], $root['type'], 0);
		}

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
		// Check depth limit
		if ($depth > $this->maxDepth) {
			return null;
		}

		$key = $objectType.'_'.$objectId;

		// Check if already visited (circular reference)
		if (isset($this->visited[$key])) {
			return null;
		}
		$this->visited[$key] = true;

		// Load object details
		$objectData = $this->loadObjectData($objectId, $objectType);
		if (!$objectData) {
			return null;
		}

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
		foreach ($children as $child) {
			$childNode = $this->buildTreeFromNode($child['id'], $child['type'], $depth + 1);
			if ($childNode !== null) {
				$node['children'][] = $childNode;
			}
		}

		return $node;
	}

	/**
	 * Load object data
	 *
	 * @param int $objectId Object ID
	 * @param string $objectType Object type
	 * @return array|false Object data or false on error
	 */
	private function loadObjectData($objectId, $objectType)
	{
		global $langs;

		// Map object types to classes
		$classMap = array(
			'facture' => 'Facture',
			'invoice' => 'Facture',
			'propal' => 'Propal',
			'commande' => 'Commande',
			'order' => 'Commande',
			'facture_fourn' => 'FactureFournisseur',
			'invoice_supplier' => 'FactureFournisseur',
			'commande_fournisseur' => 'CommandeFournisseur',
			'order_supplier' => 'CommandeFournisseur',
			'supplier_proposal' => 'SupplierProposal',
			'shipping' => 'Expedition',
			'expedition' => 'Expedition',
			'delivery' => 'Delivery',
			'contrat' => 'Contrat',
			'contract' => 'Contrat',
			'fichinter' => 'Fichinter',
			'ticket' => 'Ticket',
			'project' => 'Project',
			'project_task' => 'Task',
			'task' => 'Task',
			'stock_mouvement' => 'MouvementStock',
			'mo' => 'Mo',
			'mrp_mo' => 'Mo',
			'bom' => 'Bom',
		);

		// Map object types to file paths
		$fileMap = array(
			'facture' => '/compta/facture/class/facture.class.php',
			'invoice' => '/compta/facture/class/facture.class.php',
			'propal' => '/comm/propal/class/propal.class.php',
			'commande' => '/commande/class/commande.class.php',
			'order' => '/commande/class/commande.class.php',
			'facture_fourn' => '/fourn/class/fournisseur.facture.class.php',
			'invoice_supplier' => '/fourn/class/fournisseur.facture.class.php',
			'commande_fournisseur' => '/fourn/class/fournisseur.commande.class.php',
			'order_supplier' => '/fourn/class/fournisseur.commande.class.php',
			'supplier_proposal' => '/supplier_proposal/class/supplier_proposal.class.php',
			'shipping' => '/expedition/class/expedition.class.php',
			'expedition' => '/expedition/class/expedition.class.php',
			'delivery' => '/delivery/class/delivery.class.php',
			'contrat' => '/contrat/class/contrat.class.php',
			'contract' => '/contrat/class/contrat.class.php',
			'fichinter' => '/fichinter/class/fichinter.class.php',
			'ticket' => '/ticket/class/ticket.class.php',
			'project' => '/projet/class/project.class.php',
			'project_task' => '/projet/class/task.class.php',
			'task' => '/projet/class/task.class.php',
			'mo' => '/mrp/class/mo.class.php',
			'mrp_mo' => '/mrp/class/mo.class.php',
			'bom' => '/bom/class/bom.class.php',
		);

		if (!isset($classMap[$objectType]) || !isset($fileMap[$objectType])) {
			return array(
				'ref' => '?',
				'label' => $objectType.' #'.$objectId,
				'url' => '',
				'status' => '',
			);
		}

		$className = $classMap[$objectType];
		$filePath = $fileMap[$objectType];

		if (!class_exists($className)) {
			require_once DOL_DOCUMENT_ROOT.$filePath;
		}

		$obj = new $className($this->db);
		$result = $obj->fetch($objectId);

		if ($result > 0) {
			return array(
				'ref' => $obj->ref,
				'label' => $obj->ref.(isset($obj->label) ? ' - '.$obj->label : ''),
				'url' => $obj->getNomUrl(1),
				'status' => method_exists($obj, 'getLibStatut') ? $obj->getLibStatut(3) : '',
				'date' => isset($obj->date) ? $obj->date : (isset($obj->datec) ? $obj->datec : ''),
				'amount' => isset($obj->total_ttc) ? $obj->total_ttc : (isset($obj->total_ht) ? $obj->total_ht : ''),
			);
		}

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
		$moreHtml = '';
		if (!empty($object)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
			$form = new Form($this->db);
			
			// Get the element type to exclude (don't allow linking to same type)
			$excludeType = array($object->element);
			
			// Call Dolibarr's native function to generate the link dropdown
			// Returns array with 'linktoelem' and 'htmltoenteralink'
			$tmparray = $form->showLinkToObjectBlock($object, array(), $excludeType, 1);
			if (!empty($tmparray['htmltoenteralink'])) {
				$moreHtml = $tmparray['htmltoenteralink'];
			}
		}

		// Add proper Dolibarr section header with link action
		$html = '<br>'."\n";
		$html .= '<div class="div-table-responsive-no-min">'."\n";
		
		$html .= load_fiche_titre($langs->trans("RelatedObjects"), $moreHtml, '');
		
		// Render as table with columns
		$html .= '<table class="noborder linkedobjecttree-table centpercent">'."\n";
		
		// Table header
		$html .= '<tr class="liste_titre">';
		$html .= '<th class="linkedobjecttree-col-ref">'.$langs->trans("Ref").'</th>';
		$html .= '<th class="linkedobjecttree-col-type">'.$langs->trans("Type").'</th>';
		$html .= '<th class="linkedobjecttree-col-date">'.$langs->trans("Date").'</th>';
		$html .= '<th class="linkedobjecttree-col-amount right">'.$langs->trans("AmountHT").'</th>';
		$html .= '<th class="linkedobjecttree-col-status right">'.$langs->trans("Status").'</th>';
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
	 * Get translated name for object type
	 *
	 * @param string $type Object type
	 * @return string Translated type name
	 */
	private function getTranslatedType($type)
	{
		global $langs;
		
		// Map object types to Dolibarr translation keys
		$translationMap = array(
			'facture' => 'Invoice',
			'invoice' => 'Invoice',
			'propal' => 'Proposal',
			'commande' => 'Order',
			'order' => 'Order',
			'facture_fourn' => 'SupplierInvoice',
			'invoice_supplier' => 'SupplierInvoice',
			'commande_fournisseur' => 'SupplierOrder',
			'order_supplier' => 'SupplierOrder',
			'supplier_proposal' => 'SupplierProposal',
			'shipping' => 'Shipment',
			'expedition' => 'Shipment',
			'delivery' => 'Delivery',
			'contrat' => 'Contract',
			'contract' => 'Contract',
			'fichinter' => 'Intervention',
			'ticket' => 'Ticket',
			'project' => 'Project',
			'project_task' => 'Task',
			'task' => 'Task',
			'mo' => 'ManufacturingOrder',
			'mrp_mo' => 'ManufacturingOrder',
			'bom' => 'BOM',
		);
		
		// Get the translation key
		$translationKey = isset($translationMap[$type]) ? $translationMap[$type] : ucfirst($type);
		
		// Return the translated string
		return $langs->trans($translationKey);
	}

	/**
	 * Get icon for object type
	 *
	 * @param string $type Object type
	 * @return string Icon name
	 */
	private function getIconForType($type)
	{
		$iconMap = array(
			'facture' => 'bill',
			'invoice' => 'bill',
			'propal' => 'propal',
			'commande' => 'order',
			'order' => 'order',
			'facture_fourn' => 'supplier_invoice',
			'invoice_supplier' => 'supplier_invoice',
			'commande_fournisseur' => 'supplier_order',
			'order_supplier' => 'supplier_order',
			'supplier_proposal' => 'supplier_proposal',
			'shipping' => 'shipment',
			'expedition' => 'shipment',
			'delivery' => 'delivery',
			'contrat' => 'contract',
			'contract' => 'contract',
			'fichinter' => 'intervention',
			'ticket' => 'ticket',
			'project' => 'project',
			'project_task' => 'projecttask',
			'task' => 'projecttask',
			'mo' => 'mrp',
			'mrp_mo' => 'mrp',
			'bom' => 'bom',
		);

		return isset($iconMap[$type]) ? $iconMap[$type] : 'generic';
	}
}
