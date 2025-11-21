<?php
/* Copyright (C) 2020 Eoxia
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
 * \file    htdocs/custom/doliwpshop/class/actions_doliwpshop.class.php
 * \ingroup doliwpshop
 * \brief   Hook on new actions for connected Dolibarr and WPshop
 */

dol_include_once('/custom/doliwpshop/lib/api_doliwpshop.class.php');
dol_include_once('/custom/doliwpshop/class/product_doliwpshop.class.php');
dol_include_once('/custom/doliwpshop/class/thirdparty_doliwpshop.class.php');
dol_include_once('/custom/doliwpshop/class/category_doliwpshop.class.php');

/**
 * Class ActionsDoliWPshop
 */
class ActionsDoliWPshop
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

	/**
	 * Do new actions on CommonObject
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (third party and product object)
	 * @param   string          $action         Current action (view_on_wpshop or create_on_wpshop)
	 * @return  int                             < 0 on error, 0 on success,
	 */
	public function doActions($parameters, &$object, &$action)
	{
		global $langs, $db;
		
		// Translations
		$langs->load("doliwpshop@doliwpshop");

		$connected = true;

		if ( ! $connected ) {
			setEventMessages($langs->trans("NotConnectedWPshop"), null, 'errors');
			return 0;
		}
		
		if (in_array('productcard', explode(':', $parameters['context']))) {
			$productDoliWPshop = new ProductDoliWPshop();
			
			if ($action == 'view' && $connected === true && ! empty($object->array_options['options__wps_id']))
			{
				$productDoliWPshop->checkProductExistOnWPshop($object);
			}

			if ($action == 'createwp' && $connected === true && empty($object->array_options['options__wps_id']))
			{
				$productDoliWPshop->createProductOnWPshop($object);
			}
		} elseif (in_array('categorycard', explode(':', $parameters['context']))) {
			$categoryDoliWPshop = new CategoryDoliWPshop();
			/*
			if ($action == 'view' && $connected === true && ! empty($object->array_options['options__wps_id']))
			{
				$categoryDoliWPshop->checkCategoryExistOnWPshop($object);
			}
			*/
			if ($action == 'createwp' && $connected === true && empty($object->array_options['options__wps_id']))
			{
				$categoryDoliWPshop->createCategoryOnWPshop($object);
			}

		} elseif (in_array('thirdpartycard', explode(':', $parameters['context']))) {
			$thirdpartyDoliWPshop = new ThirdPartyDoliWPshop();

			if ($action == 'view' && $connected === true && ! empty($object->array_options['options__wps_id']))
			{
				$thirdpartyDoliWPshop->checkThirdPartyExistOnWPshop($object);
			}

			if ($action == 'createwp' && $connected === true && empty($object->array_options['options__wps_id']))
			{
				$thirdpartyDoliWPshop->createThirdPartyOnWPshop($object);
			}
		} elseif (in_array('producttranslationcard', explode(':', $parameters['context']))) {
			global $conf, $user;

			if ($action == 'delete'){
				if ($conf->global->WPSHOP_DATA_ARCHIVE_ON_DELETION) {
					$id           = GETPOST('id', 'alpha');
					$langtodelete = GETPOST('langtodelete', 'alpha');

					require_once __DIR__.'/productlang.class.php';

					$productLang = new ProductLang($this->db);
					$productLangData = array_shift($productLang->fetchAll('', 't.rowid', 0, 0, array('t.fk_product'=>$id,'t.lang'=>$langtodelete), 'AND'));
					$productLangDataID = array();
					$productLangDataID['id'] = $productLangData->array_options['options_wpshopidtradmultilangs'];
					WPshopAPI::post('/wp-json/wpshop/v2/wpml_delete_data',$productLangDataID);

					require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
					$now = dol_now();
					$actioncomm = new ActionComm($this->db);

					$actioncomm->elementtype = 'product';
					$actioncomm->code        = 'AC_ARCHIVE_DATA_TRAD';
					$actioncomm->type_code   = 'AC_OTH_AUTO';
					$actioncomm->label       = $langs->trans('TranslateProductArchive');
					$actioncomm->datep       = $now;
					$actioncomm->fk_element  = $productLangData->fk_product;
					$actioncomm->userownerid = $user->id;
					$actioncomm->percentage  = -1;
					$actioncomm->note = 'Label: '.$productLangData->label.'<br>'.'Description: '. $productLangData->description;

					$actioncomm->create($user);
				}
			}
		} elseif (strpos($parameters['context'], 'productpricecard') !== false) {
			if ($action == 'update_price' && !empty(GETPOST('_wps_multishop'))) {
				$multishop = GETPOST('_wps_multishop');
				$datetime = GETPOSTDATE('_wps_end_date');

				$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'product_price as p';
				$sql .= ' ORDER BY p.tms DESC LIMIT 1';

				$resql = $db->query($sql);
				$obj   = $db->fetch_object($resql);
				if ($obj && $obj->rowid) {
					$newid = $obj->rowid + 1;

					$sql    = 'INSERT INTO ' . MAIN_DB_PREFIX . 'product_price_extrafields(fk_object, _wps_multishop, _wps_end_date) VALUES';
					$sql   .= '(' . $newid . ', "' . implode(', ', $multishop) . '", ' . $datetime . ')';
					$resql2 = $db->query($sql);
 				}

			}
		}

 		return 0;
	}

	/**
	 * Add new actions buttons on CommonObject
	 *
	 * @param   CommonObject  $object  The object to process (third party and product object)
	 */
    public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $langs;
	
		// Translations
		$langs->load("doliwpshop@doliwpshop");

		$connected = WPshopAPI::get('/wp-json/wpshop/v2/statut');
		
		if ($connected !== true) {
			print '<div class="inline-block divButAction"><a class="butActionRefused" title="'.$langs->trans("NotAvailableDolibarr").'" href="#">'.$langs->trans("CreateOnWPshop").'</a></div>';
			return;
		}
		
		if (empty($object->array_options['options__wps_id'])) {

			if ( isset( $_SERVER['HTTPS'] ) ) {
				if ( $_SERVER['HTTPS'] == 'on' ) {
				  $server_protocol = 'https';
				} else {
				  $server_protocol = 'http';
				} 
			} else {
				$server_protocol = 'http';
			  }
		
			$actual_link = $server_protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$actual_link .= '&action=createwp';
			print '<div class="inline-block divButAction"><a class="butAction" href="' . $actual_link . '">'.$langs->trans("CreateOnWPshop").'</a></div>';

		} else {
			if ($object->element == 'product' ) {
				print '<div class="inline-block divButAction"><a class="butAction" title="'.$langs->trans("ViewOnWPshop").'" href="' . $conf->global->WPSHOP_URL_WORDPRESS . '/?post_type=wps-product&p=' . $object->array_options['options__wps_id'] . '" target="_blank" >'.$langs->trans("ViewOnWPshop").'</a></div>';
			} elseif ($object->element == 'societe' ) {
				print '<div class="inline-block divButAction"><a class="butAction" title="'.$langs->trans("ViewOnWPshop").'" href="' . $conf->global->WPSHOP_URL_WORDPRESS . 'wp-admin/admin.php?page=wps-third-party&id=' . $object->array_options['options__wps_id'] . '" target="_blank" >'.$langs->trans("ViewOnWPshop").'</a></div>';
			}
			print '<div class="inline-block divButAction"><a class="butActionRefused" title="'.$langs->trans("NotAvailableObject").'" href="#">'.$langs->trans("CreateOnWPshop").'</a></div>';
		}
	}

	/**
     * Overloading the printCommonFooter function : replacing the parent's function with the one below
     *
     * @param  array     $parameters Hook metadatas (context, etc...)
     * @return int                   0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function printCommonFooter(array $parameters): int
    {
        global $langs, $user, $object, $db, $action;

		if (strpos($parameters['context'], 'productpricecard') !== false) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

			if ($action == '' || $action == 'delete') {

				$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'product_price as p';
				$sql .= ' WHERE p.fk_product = ' . GETPOST('id') . ' ORDER BY p.tms DESC';

				$object = new Product($db);


				$resql = $object->db->query($sql);

				$list = [];
				$num = $object->db->num_rows($resql);
				for ($i = 0; $i < $num; $i++) {
					$obj = $object->db->fetch_object($resql);
					$obj->table_element = 'product_price';

					$sql = "SELECT e._wps_multishop FROM " . MAIN_DB_PREFIX . 'product_price_extrafields as e WHERE fk_object = ' . $obj->rowid . ' LIMIT 1';
					$resql2 = $object->db->query($sql);
					$extras = $object->db->fetch_object($resql2);
					$obj->array_options = (array) ($extras ?? []);

					$list[] = $obj;
				}
				?>
				<script>
					$(document).ready(() => {
						const phpArray = <?= json_encode($list); ?>;

						let $table = $('.div-table-responsive table').first();

						$table.find('th.right').last().before('<th><?=  $langs->trans('MultiShop') ?></th>');
						$table.find('th').first().after('<th><?= $langs->trans('PricePacticedFrom') ?></th>')

						$table.find('tr').each((index, element) => {
							if (index == 0) return;
							$(element).find('td').last().before(`<td>${phpArray[index - 1].array_options._wps_multishop ?? ''}</td>`)
							$(element).find('td').first().after(`<td>${phpArray[index - 1].array_options._wps_end_date ?? ''}</td>`);
						})
					})
				</script>
				<?php
			} elseif ($action == 'edit_price') {

				require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

				$config  = json_decode(getDolGlobalString('DOLIWPSHOP_CONFIG_JSON'), true) ?? [];

				$hosts = array_map(fn($i) => parse_url($i['ApiUrl'], PHP_URL_HOST), $config);
				$result = array_combine($hosts, $hosts);

				$selectArray = Form::multiselectarray('_wps_multishop', $result, [], 0, 0, '', 0, 150);
				$datePicker = Form::selectDate('', '_wps_end_date');

				?>
				<script>
					$(document).ready(() => {
						const phpArray = <?= json_encode($list); ?>;

						let $table = $('form table').first();

						$tr = $('<tr><td><?=  $langs->trans('MultiShop') ?></td></tr>')
						$tr.append(<?= json_encode($selectArray) ?>)
						$table.find('tbody tr').last().after($tr);

						$tr = $('<tr><td><?= $langs->trans('PricePacticedFrom') ?></td></tr>')
						$tr.append(<?=  json_encode($datePicker) ?>)
						$table.find('tbody tr').last().after($tr);
					})
				</script>
				<?php
			}

		}

        return 0; // or return 1 to replace standard code
    }
}
