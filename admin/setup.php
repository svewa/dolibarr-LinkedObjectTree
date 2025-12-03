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
 * \file       admin/setup.php
 * \ingroup    linkedobjecttree
 * \brief      Setup page for Linked Object Tree module
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Translations
$langs->loadLangs(array("admin", "linkedobjecttree@linkedobjecttree"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');

/*
 * Actions
 */

if ($action == 'setmaxdepth') {
	$maxdepth = GETPOST('maxdepth', 'int');
	if ($maxdepth >= 1 && $maxdepth <= 50) {
		dolibarr_set_const($db, "LINKEDOBJECTTREE_MAX_DEPTH", $maxdepth, 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("ErrorFieldFormat", $langs->transnoentitiesnoconv("MaxDepth")), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

/*
 * View
 */

$page_name = "LinkedObjectTreeSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = array();
$h = 0;

$head[$h][0] = dol_buildpath("/linkedobjecttree/admin/setup.php", 1);
$head[$h][1] = $langs->trans("Settings");
$head[$h][2] = 'settings';
$h++;

print dol_get_fiche_head($head, 'settings', '', -1, '');

// About section
print '<div class="info">';
print $langs->trans("LinkedObjectTreeAbout");
print '</div>';
print '<br>';

// Settings form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setmaxdepth">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Maximum depth
print '<tr class="oddeven">';
print '<td>';
print $langs->trans("MaxDepth");
print '<br><span class="opacitymedium">'.$langs->trans("MaxDepthHelp").'</span>';
print '</td>';
print '<td>';
$maxdepth = !empty($conf->global->LINKEDOBJECTTREE_MAX_DEPTH) ? $conf->global->LINKEDOBJECTTREE_MAX_DEPTH : 10;
print '<input type="number" name="maxdepth" value="'.$maxdepth.'" min="1" max="50" class="flat width50">';
print ' <input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
