<?php
/* Copyright (C) 2019-2020 Eoxia <dev@eoxia.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/doliwpshop/admin/doliwpshop.php
 * \ingroup doliwpshop
 * \brief   Page setup for DoliWpshop module.
 */

// Load Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
	$res = @include("../../../main.inc.php"); // From "custom" directory
}
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/doliwpshop.lib.php';
require_once '../lib/api_doliwpshop.class.php';

require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

// Translations
$langs->loadLangs(array("admin", "doliwpshop@doliwpshop"));

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$value      = GETPOST('value', 'alpha');

$arrayofparameters = array(
	'WPSHOP_URL_WORDPRESS'      => array('css'=> 'minwidth500', 'enabled' => 1),
	'WPSHOP_TOKEN'              => array('css'=> 'minwidth500', 'enabled'=> 1),
);

$tmpCategorie = new Categorie($db);

$userapi = new User($db);
$userapi->fetch($conf->global->DOLIWPSHOP_USERAPI_SET,'', '',0,$conf->entity);
$userapi->getrights();
//Rights invoices
$userapi->rights->facture->lire ? 1 : $userapi->addrights(11);
$userapi->rights->facture->creer ? 1 : $userapi->addrights(12);
$userapi->rights->facture->paiment ? 1 : $userapi->addrights(16);
//Rights propals
$userapi->rights->propale->lire ? 1 : $userapi->addrights(21);
$userapi->rights->propale->creer ? 1 : $userapi->addrights(22);
$userapi->rights->propale->cloturer ? 1 : $userapi->addrights(26);
//Rights products
$userapi->rights->produit->lire ? 1 : $userapi->addrights(31);
$userapi->rights->produit->creer ? 1 : $userapi->addrights(32);
//Rights orders
$userapi->rights->commande->lire ? 1 : $userapi->addrights(81);
$userapi->rights->commande->creer ? 1 : $userapi->addrights(82);
//Rights tiers
$userapi->rights->societe->lire ? 1 : $userapi->addrights(121);
$userapi->rights->societe->creer ? 1 : $userapi->addrights(122);
$userapi->rights->societe->supprimer ? 1 : $userapi->addrights(125);
$userapi->rights->societe->exporter ? 1 : $userapi->addrights(126);
$userapi->rights->societe->client->voir ? 1 : $userapi->addrights(262);
$userapi->rights->societe->contact->lire ? 1 : $userapi->addrights(281);
//Rights tags
$userapi->rights->categorie->lire ? 1 : $userapi->addrights(241);
$userapi->rights->categorie->creer ? 1 : $userapi->addrights(242);
//Rights services
$userapi->rights->service->lire ? 1 : $userapi->addrights(531);
$userapi->rights->service->creer ? 1 : $userapi->addrights(532);
//Rights stocks
$userapi->rights->stock->lire ? 1 : $userapi->addrights(1001);
//Rights events
$userapi->rights->agenda->myactions->read ? 1 : $userapi->addrights(2401);
$userapi->rights->propale->myactions->create  ? 1 : $userapi->addrights(2402);
$userapi->rights->propale->myactions->delete  ? 1 : $userapi->addrights(2403);

/*
 * Actions
 */
if ((float) DOL_VERSION >= 6) {
	include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';
}

if ($action == 'add') {
	// Load current configuration
	$config = json_decode(getDolGlobalString('DOLIWPSHOP_CONFIG_JSON'), true) ?? [];
	
	// Add new empty configuration entry
	$config[] = [
		'ApiUrl' => 'https://example.com',
		'ApiToken' => 'exemple_token',
		'DomainTagId' => ''
	];
	
	// Save updated configuration
	dolibarr_set_const($db, "DOLIWPSHOP_CONFIG_JSON", json_encode($config), 'chaine', 0, '', $conf->entity);
	
	// Redirect to edit the new entry
	$newConfigId = count($config) - 1;
	header("Location: ".$_SERVER["PHP_SELF"]."?action=edit&config_id=".$newConfigId);
	exit;
}

if (($action == 'update' && !GETPOST("cancel", 'alpha')) || ($action == 'updateedit'))
{
	$config_id = GETPOST('config_id', 'int');
	$WPSHOP_URL_WORDPRESS = GETPOST('WPSHOP_URL_WORDPRESS','alpha');
	$WPSHOP_TOKEN = GETPOST('WPSHOP_TOKEN','alpha');
	$data_archive_on_deletion = GETPOST('data_archive_on_deletion','alpha');

	// Load current configuration
	$config = json_decode(getDolGlobalString('DOLIWPSHOP_CONFIG_JSON'), true) ?? [['ApiUrl' => 'https://example.com', 'ApiToken' => 'exemple_token', 'DomainTagId' => '']];
	
	// Update the specific configuration entry
	if (isset($config[$config_id])) {
		$config[$config_id]['ApiUrl'] = $WPSHOP_URL_WORDPRESS;
		$config[$config_id]['ApiToken'] = $WPSHOP_TOKEN;

		if (empty($config[$config_id]['DomainTagId'])) {
			$tmpCategorie->type = 'product';
			$tmpCategorie->label = parse_url($WPSHOP_URL_WORDPRESS, PHP_URL_HOST);
			$tmpCategorieId = $tmpCategorie->create($user);
			$config[$config_id]['DomainTagId'] = $tmpCategorieId;
		} else {
			$tmpCategorie->fetch($config[$config_id]['DomainTagId']);
			$tmpCategorie->label = parse_url($WPSHOP_URL_WORDPRESS, PHP_URL_HOST);
			$tmpCategorie->update($user);
		}

	}

	// Save updated configuration
	dolibarr_set_const($db, "DOLIWPSHOP_CONFIG_JSON", json_encode($config), 'chaine', 0, '', $conf->entity);

	$link = '<a href="'.$WPSHOP_URL_WORDPRESS.'">'.$langs->trans("PaymentMessage").'</a>';
	dolibarr_set_const($db, "ONLINE_PAYMENT_MESSAGE_OK", $link, 'integer', 0, '', $conf->entity);
	dolibarr_set_const($db, "WPSHOP_DATA_ARCHIVE_ON_DELETION", $data_archive_on_deletion, 'integer', 0, '', $conf->entity);

	if ($action != 'updateedit' && !$error)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
}

/*
 * View
 */
$page_name = "DoliWPshopSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT .'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_doliwpshop@doliwpshop');

// Configuration header
$head = doliwpshopAdminPrepareHead();
dol_get_fiche_head($head, 'settings', '', -1, "doliwpshop@doliwpshop");

// Setup page goes here
echo $langs->trans("DoliWPshopSetupPage").'<br><br>';

$config = json_decode(getDolGlobalString('DOLIWPSHOP_CONFIG_JSON'), true) ?? [['ApiUrl' => 'https://example.com', 'ApiToken' => 'exemple_token', 'DomainTagId' => '']];

if (empty($action)) {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=add"><i class="fas fa-plus"></i></a>';
	print '</div>';
}

foreach ($config as $key => $value) {

	if ($action == 'edit' && $key == GETPOST('config_id','int')) {

		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';

		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';

		print '<input type="hidden" name="config_id" value="'.$key.'">';

		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($langs->trans('WPSHOP_URL_WORDPRESS'),$langs->trans('WPSHOP_URL_WORDPRESSTooltip'));
		print '</td><td><input type="text" size="50" name="WPSHOP_URL_WORDPRESS" value="'.$value['ApiUrl'].'"></td></tr>';

		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($langs->trans('WPSHOP_TOKEN'),$langs->trans('WPSHOP_TOKENTooltip'));
		print '</td><td><input type="text" size="50" name="WPSHOP_TOKEN" value="'.$value['ApiToken'].'"></td></tr>';

		print '<tr><td>'.$langs->trans("DataArchiveOnDeletion").'</td><td>';
		print '<input type="checkbox" id="data_archive_on_deletion" name="data_archive_on_deletion" '.($conf->global->WPSHOP_DATA_ARCHIVE_ON_DELETION ? ' checked=""' : '').'>';
		print '</td></tr>';

		print '</table>';

		print '<div class="tabsAction">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
		print '<input type="submit" class="butActionDelete" name="cancel" value="'.$langs->trans("Cancel").'">';
		print '</div>';

		print '</form>';
	} elseif ($action != 'edit') {

		print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

		print '<tr class="oddeven"><td>';
		print $langs->trans("DomainNameSlug");
		print '</td><td>' . parse_url($value['ApiUrl'], PHP_URL_HOST) . '</td></tr>';
	
		print '<tr class="oddeven"><td>';
		print $langs->trans('DomainTag') . '</td>';
		if (!empty($value['DomainTagId'])) {
			$tmpCategorie->fetch($value['DomainTagId']);
			$url = DOL_URL_ROOT.'/categories/viewcat.php?id='.$tmpCategorie->id.'&type='.$tmpCategorie->type.'&backtopage='.urlencode($_SERVER['PHP_SELF']);
			print '<td><a href="' . $url . '" class="wpeo-link">' . img_object('', $tmpCategorie->picto) . ' ' . $tmpCategorie->label . '</a></td>';
		} else {
			print '<td>-</td>';
		}
		print '</tr>';
	
		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($langs->trans('WPSHOP_URL_WORDPRESS'),$langs->trans('WPSHOP_URL_WORDPRESSTooltip'));
		print '</td><td>' . $value['ApiUrl'] . '</td></tr>';
	
		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($langs->trans('WPSHOP_TOKEN'),$langs->trans('WPSHOP_TOKENTooltip'));
		print '</td><td>' . str_repeat('*', strlen($value['ApiToken'])) . '</td></tr>';
	
	
		print '<tr class="oddevent"><td>'.$langs->trans("CommunicationWordPress").'</td><td>';
		
		if ( WPshopAPI::get('/wp-json/wpshop/v2/statut', $value['ApiUrl']) === true ) {
			echo $langs->trans("ConnectedWordPress") . ' <i class="fas fa-check" style="color: green;"></i>';
		} else {
			echo $langs->trans("FailureWordPress") . ' <i class="fas fa-times" style="color: red;"></i>';
		}
		print '</td></tr>';
	
		print '<tr><td>'.$langs->trans("DataArchiveOnDeletion").'</td><td>';
		print '<input type="checkbox" id="data_archive_on_deletion" name="data_archive_on_deletion" '.($conf->global->WPSHOP_DATA_ARCHIVE_ON_DELETION ? ' checked=""' : '').' disabled>';
		print '</td></tr>';
	
		print '<tr><td>'.$langs->trans("ActivateTranslateLink").'</td><td>';
		print '<a href="'.DOL_MAIN_URL_ROOT.'/admin/ihm.php?mainmenu=home" target="_blank">'.DOL_MAIN_URL_ROOT.'/admin/ihm.php?mainmenu=home</a>';
		print '</td></tr>';
	
		print '</table>';
	
		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&config_id='.$key.'">'.$langs->trans("Modify").'</a>';
		print '</div>';
	}
}



// Page end
dol_get_fiche_end();

llxFooter();
$db->close();
