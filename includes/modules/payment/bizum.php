<?php

use util\order_tools as order_tools;

//Logs Redsys - Funciones
if (!function_exists("escribirLog")) {
	require_once 'apiRedsys/redsysLibrary.php';
}

//Classe API Redsys
if (!class_exists("RedsysAPI")) {
	require_once 'apiRedsys/apiRedsysFinal.php';
}

class bizum
{
	public $code, $title, $description, $enabled;

	// class constructor
	public function __construct()
	{
		global $order;

		$this->code = 'bizum';
		$this->title = MODULE_PAYMENT_BIZUM_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_BIZUM_TEXT_DESCRIPTION;

		//Comprobamos si esta o no insalado el módulo
		if ($_GET['action'] != 'install' && $this->check() != '1') {
			return false;
		}

		$this->enabled = ((MODULE_PAYMENT_BIZUM_STATUS == 'True') ? true : false);
		$this->sort_order = MODULE_PAYMENT_BIZUM_SORT_ORDER;
		$this->mantener_pedido_ante_error_pago = ((MODULE_PAYMENT_BIZUM_ERROR_PAGO == 'si') ? true : false);
		$this->logActivo = MODULE_PAYMENT_BIZUM_LOG;

		if ((int) MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID > 0) {
			$this->order_status = MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID;
		}

		if (is_object($order)) {
			$this->update_status();
		}

		//Seleccion del entorno de pago
		if (MODULE_PAYMENT_BIZUM_ENTORNO == "Entorno Real") {
			$this->form_action_url = "https://sis.redsys.es/sis/realizarPago/utf-8";
		} else if (MODULE_PAYMENT_BIZUM_ENTORNO == "Entorno Pruebas") {
			$this->form_action_url = "https://sis-t.redsys.es:25443/sis/realizarPago";
		}
	}

	// class methods
	public function update_status()
	{
		global $order;

		if (($this->enabled == true) && defined('MODULE_PAYMENT_BIZUM_ZONE') && ((int) MODULE_PAYMENT_BIZUM_ZONE > 0)) {
			$check_flag = false;
			$check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_BIZUM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
			while ($check = tep_db_fetch_array($check_query)) {
				if ($check['zone_id'] < 1) {
					$check_flag = true;
					break;
				} elseif ($check['zone_id'] == $order->billing['zone_id']) {
					$check_flag = true;
					break;
				}
			}

			if ($check_flag == false) {
				$this->enabled = false;
			}
		}
	}

	public function javascript_validation()
	{
		return false;
	}

	public function selection()
	{
		return array(
			'id' => $this->code,
			'module' => $this->title
		);
	}

	public function pre_confirmation_check()
	{
		global $cartID, $cart;

		if (empty($cart->cartID)) {
			$cartID = $cart->cartID = $cart->generate_cart_id();
		}

		if (!tep_session_is_registered('cartID')) {
			tep_session_register('cartID');
		}
	}

	public function confirmation()
	{
		return false;
	}

	public function process_button()
	{
		global $order, $currency, $language;
		$numpedido = "1" . time();

		//Amount
		$total = $order->info['total'];
		$cantidad = round($total * $order->info['currency_value'], 2);
		$cantidad = number_format($cantidad, 2, '.', '');
		$cantidad = preg_replace('/\./', '', $cantidad);

		//Terminal
		$terminal = MODULE_PAYMENT_BIZUM_TERMINAL;

		// Tipo de trans.
		$trans = "0";

		//Idioma
		$idioma = MODULE_PAYMENT_BIZUM_IDIOMA;

		if ($idioma == "Si") {
			$idioma_web = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);

			switch ($idioma_web) {
				case 'es':
					$idiomaFinal = '001';
					break;
				case 'en':
					$idiomaFinal = '002';
					break;
				case 'ca':
					$idiomaFinal = '003';
					break;
				case 'fr':
					$idiomaFinal = '004';
					break;
				case 'de':
					$idiomaFinal = '005';
					break;
				case 'nl':
					$idiomaFinal = '006';
					break;
				case 'it':
					$idiomaFinal = '007';
					break;
				case 'sv':
					$idiomaFinal = '008';
					break;
				case 'pt':
					$idiomaFinal = '009';
					break;
				case 'pl':
					$idiomaFinal = '011';
					break;
				case 'gl':
					$idiomaFinal = '012';
					break;
				case 'eu':
					$idiomaFinal = '013';
					break;
				default:
					$idiomaFinal = '002';
			}
			$idioma_tpv = $idiomaFinal;
		} else {
			$idioma_tpv = "0";
		}

		//Merchant URL
		$urltienda = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
		$idSesion = tep_session_id();
		$urltienda = $urltienda . "?osCsid=" . $idSesion;
		$clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;

		//FUC
		$codigo = MODULE_PAYMENT_BIZUM_ID_COM;

		//URL_KO y URL_OK
		$ds_merchant_urlok = tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
		$ds_merchant_urlko = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . "ERROR: Lo sentimos, pero no ha sido posible procesar su pago. Inténtelo de nuevo o pruebe con otro de los métodos de pago disponibles.", 'SSL');

		//Merchant Data
		$ds_merchant_data = sha1($urltienda);

		//Tipo de Pago Z =. Bizum
		$tipopago = "z";

		//Moneda
		if (MODULE_PAYMENT_BIZUM_CURRENCY == "Euro") {
			$moneda = "978";
		} else {
			$moneda = "840";
		}

		//Datos Grabar Pedido
		$descripcionTransaccion = 'Pedido ' . $order->customer['firstname'] . ' ' . $order->customer['lastname'] . ' - ' . $order->customer['email_address'] . '';

		//Firma
		$clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;
		$ds_merchant_name = MODULE_PAYMENT_BIZUM_NOMBRE;

		$miObj = new RedsysAPI;
		$miObj->setParameter("DS_MERCHANT_AMOUNT", $cantidad);
		$miObj->setParameter("DS_MERCHANT_ORDER", strval($numpedido));
		$miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $codigo);
		$miObj->setParameter("DS_MERCHANT_CURRENCY", $moneda);
		$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", $trans);
		$miObj->setParameter("DS_MERCHANT_TERMINAL", $terminal);
		$miObj->setParameter("DS_MERCHANT_MERCHANTURL", $urltienda);
		$miObj->setParameter("DS_MERCHANT_URLOK", $ds_merchant_urlok);
		$miObj->setParameter("DS_MERCHANT_URLKO", $ds_merchant_urlko);
		$miObj->setParameter("Ds_Merchant_ConsumerLanguage", $idioma_tpv);
		$miObj->setParameter("Ds_Merchant_ProductDescription", $descripcionTransaccion);
		$miObj->setParameter("Ds_Merchant_Titular", $ds_merchant_name);
		$miObj->setParameter("Ds_Merchant_MerchantData", $ds_merchant_data);
		$miObj->setParameter("Ds_Merchant_MerchantName", $ds_merchant_name);
		$miObj->setParameter("Ds_Merchant_PayMethods", $tipopago);
		$miObj->setParameter("Ds_Merchant_Module", "oscDenox");

		//Datos de configuración
		$version = "HMAC_SHA256_V1";

		//Clave del comercio que se extrae de la configuración del comercio
		// Se generan los parámetros de la petición
		$request = "";
		$paramsBase64 = $miObj->createMerchantParameters();
		$signatureMac = $miObj->createMerchantSignature($clave256);

		// Elementos del Form al SIS
		$process_button_string =
			tep_draw_hidden_field('Ds_SignatureVersion', $version) .
			tep_draw_hidden_field('Ds_MerchantParameters', $paramsBase64) .
			tep_draw_hidden_field('Ds_Signature', $signatureMac);

		return $process_button_string;
	}

	public function extra_payment_process_button()
	{
		global $nTotalExtra, $sExtraName, $sExtraEmail, $currency, $language;
		$numpedido = "1" . time();

		//Amount
		$total = $nTotalExtra;
		$cantidad = round($total, 2);
		$cantidad = number_format($cantidad, 2, '.', '');
		$cantidad = preg_replace('/\./', '', $cantidad);

		//Terminal
		$terminal = MODULE_PAYMENT_BIZUM_TERMINAL;

		// Tipo de trans.
		$trans = "0";

		//Idioma
		$idioma = MODULE_PAYMENT_BIZUM_IDIOMA;

		if ($idioma == "Si") {
			$idioma_web = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);

			switch ($idioma_web) {
				case 'es':
					$idiomaFinal = '001';
					break;
				case 'en':
					$idiomaFinal = '002';
					break;
				case 'ca':
					$idiomaFinal = '003';
					break;
				case 'fr':
					$idiomaFinal = '004';
					break;
				case 'de':
					$idiomaFinal = '005';
					break;
				case 'nl':
					$idiomaFinal = '006';
					break;
				case 'it':
					$idiomaFinal = '007';
					break;
				case 'sv':
					$idiomaFinal = '008';
					break;
				case 'pt':
					$idiomaFinal = '009';
					break;
				case 'pl':
					$idiomaFinal = '011';
					break;
				case 'gl':
					$idiomaFinal = '012';
					break;
				case 'eu':
					$idiomaFinal = '013';
					break;
				default:
					$idiomaFinal = '002';
			}
			$idioma_tpv = $idiomaFinal;
		} else {
			$idioma_tpv = "0";
		}

		//Merchant URL
		$idSesion = tep_session_id();
		$urltienda = tep_href_link('extra_payment/process/', '', 'SSL') . '?' . tep_array_to_string(array_merge(array('osCsid' => $idSesion), $_GET));
		$clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;

		//FUC
		$codigo = MODULE_PAYMENT_BIZUM_ID_COM;

		//URL_KO y URL_OK
		$ds_merchant_urlok = tep_href_link('extra_payment/success/', tep_array_to_string($_GET, array(tep_session_name())), 'SSL');
		$ds_merchant_urlko = tep_href_link('extra_payment/payment/', tep_array_to_string(array_merge($_GET, array('error_message' => "ERROR: Lo sentimos, pero no ha sido posible procesar su pago. Inténtelo de nuevo o pruebe con otro de los métodos de pago disponibles.")), array(tep_session_name())), 'SSL');

		//Merchant Data
		$ds_merchant_data = sha1($urltienda);

		//Tipo de Pago Z =. Bizum
		$tipopago = "z";

		//Moneda
		if (MODULE_PAYMENT_BIZUM_CURRENCY == "Euro") {
			$moneda = "978";
		} else {
			$moneda = "840";
		}

		//Datos Grabar Pedido
		$descripcionTransaccion = 'Pago adicional: ' . $sExtraName . ' - ' . $sExtraEmail;

		//Firma
		$clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;
		$ds_merchant_name = MODULE_PAYMENT_BIZUM_NOMBRE;

		$miObj = new RedsysAPI;
		$miObj->setParameter("DS_MERCHANT_AMOUNT", $cantidad);
		$miObj->setParameter("DS_MERCHANT_ORDER", strval($numpedido));
		$miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $codigo);
		$miObj->setParameter("DS_MERCHANT_CURRENCY", $moneda);
		$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", $trans);
		$miObj->setParameter("DS_MERCHANT_TERMINAL", $terminal);
		$miObj->setParameter("DS_MERCHANT_MERCHANTURL", $urltienda);
		$miObj->setParameter("DS_MERCHANT_URLOK", $ds_merchant_urlok);
		$miObj->setParameter("DS_MERCHANT_URLKO", $ds_merchant_urlko);
		$miObj->setParameter("Ds_Merchant_ConsumerLanguage", $idioma_tpv);
		$miObj->setParameter("Ds_Merchant_ProductDescription", $descripcionTransaccion);
		$miObj->setParameter("Ds_Merchant_Titular", $ds_merchant_name);
		$miObj->setParameter("Ds_Merchant_MerchantData", $ds_merchant_data);
		$miObj->setParameter("Ds_Merchant_MerchantName", $ds_merchant_name);
		$miObj->setParameter("Ds_Merchant_PayMethods", $tipopago);
		$miObj->setParameter("Ds_Merchant_Module", "oscDenox");

		//Datos de configuración
		$version = "HMAC_SHA256_V1";

		//Clave del comercio que se extrae de la configuración del comercio
		// Se generan los parámetros de la petición
		$request = "";
		$paramsBase64 = $miObj->createMerchantParameters();
		$signatureMac = $miObj->createMerchantSignature($clave256);

		// Elementos del Form al SIS
		$process_button_string =
			tep_draw_hidden_field('Ds_SignatureVersion', $version) .
			tep_draw_hidden_field('Ds_MerchantParameters', $paramsBase64) .
			tep_draw_hidden_field('Ds_Signature', $signatureMac);

		return $process_button_string;
	}


	public function before_process()
	{
		global $customer_id;

		$idLog = generateIdLog();
		$logActivo = MODULE_PAYMENT_BIZUM_LOG;
		$valido = false;
		if (!empty($_POST)) { //URL DE RESP. ONLINE

			$clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;

			/** Recoger datos de respuesta **/
			$version = $_POST["Ds_SignatureVersion"];
			$datos = $_POST["Ds_MerchantParameters"];
			$firma_remota = $_POST["Ds_Signature"];

			// Se crea Objeto
			$miObj = new RedsysAPI;

			/** Se decodifican los datos enviados y se carga el array de datos **/
			$decodec = $miObj->decodeMerchantParameters($datos);

			/** Se calcula la firma **/
			$firma_local = $miObj->createMerchantSignatureNotif($clave256, $datos);

			/** Extraer datos de la notificación **/
			$total = $miObj->getParameter('Ds_Amount');
			$pedido = $miObj->getParameter('Ds_Order');
			$codigo = $miObj->getParameter('Ds_MerchantCode');
			$moneda = $miObj->getParameter('Ds_Currency');
			$respuesta = $miObj->getParameter('Ds_Response');
			$id_trans = $miObj->getParameter('Ds_AuthorisationCode');

			//Nuevas variables
			$codigoOrig = MODULE_PAYMENT_BIZUM_ID_COM;

			if (
				checkRespuesta($respuesta)
				&& checkMoneda($moneda)
				&& checkFuc($codigo)
				&& checkPedidoNum($pedido)
				&& checkImporte($total)
				&& $codigo == $codigoOrig
			) {
				escribirLog($idLog . " -- El pedido con ID " . $pedido . " es válido y se ha registrado correctamente.", $logActivo);
				$valido = true;
			} else {
				escribirLog($idLog . " -- Parámetros incorrectos.", $logActivo);
				if (!checkImporte($total)) {
					escribirLog($idLog . " -- Formato de importe incorrecto.", $logActivo);
				}
				if (!checkPedidoNum($pedido)) {
					escribirLog($idLog . " -- Formato de nº de pedido incorrecto.", $logActivo);
				}
				if (!checkFuc($codigo)) {
					escribirLog($idLog . " -- Formato de FUC incorrecto.", $logActivo);
				}
				if (!checkMoneda($moneda)) {
					escribirLog($idLog . " -- Formato de moneda incorrecto.", $logActivo);
				}
				if (!checkRespuesta($respuesta)) {
					escribirLog($idLog . " -- Formato de respuesta incorrecto.", $logActivo);
				}
				if (!checkFirma($firma_remota)) {
					escribirLog($idLog . " -- Formato de firma incorrecto.", $logActivo);
				}
				escribirLog($idLog . " -- El pedido con ID " . $pedido . " NO es válido.", $logActivo);
				$valido = false;
			}

			if ($firma_local != $firma_remota || false === $valido) {
				//El proceso no puede ser completado, error de autenticación
				escribirLog($idLog . " -- La firma no es correcta.", $logActivo);
				die("FALLO DE FIRMA");
				exit;
			}

			$iresponse = (int) $respuesta;

			if (($iresponse >= 0) && ($iresponse <= 100)) {
				//Transacción aprobada
				//after_process();

				order_tools::before_process(
					'bizum',
					[
						'reference' => $pedido,
						'value' => floatval(intval($total) / 100),
						'customer_id' => intval($customer_id),
					]
				);
			} else {
				//Transacción denegada
				if (!$this->mantener_pedido_ante_error_pago) {
					$_SESSION['cart']->reset(true);
					escribirLog($idLog . " -- Error de respuesta. Vaciando carrito.", $logActivo);
				} else {
					escribirLog($idLog . " -- Error de respuesta. Manteniendo carrito.", $logActivo);
				}
				die("FALLO EN LA RESPUESTA");
				exit;
			}
		} else {
			//Transacción denegada
			escribirLog($idLog . " -- Error. Hacking attempt!", $logActivo);
			die("Hacking attempt!");
			exit;
		}
	}

	public function after_process()
	{
		global $order, $insert_id, $cart, $customer_id;

		if (tep_session_is_registered('cartID')) {
			$cart->reset(true);
			tep_db_query("update " . TABLE_ORDERS_STATUS_HISTORY . " set orders_status_id = " . MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID . " where orders_id = '" . (int) $insert_id . "'");
			tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID . ", last_modified = now() where orders_id = '" . (int) $insert_id . "'");


			if (!empty($_POST) && isset($_POST["Ds_MerchantParameters"])) {
				order_tools::after_process((int)$insert_id, (int)$customer_id);
			}
		}
	}

	public function output_error()
	{
		return false;
	}

	public function check()
	{
		if (!isset($this->_check)) {
			$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BIZUM_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
	}

	//Instalar módulo
	public function install()
	{
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar pasarela TPV', 'MODULE_PAYMENT_BIZUM_STATUS', 'True', 'Aceptar pagos mediante Tarjeta de Crédito', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Nombre del Comercio', 'MODULE_PAYMENT_BIZUM_NOMBRE', '', 'Nombre del comercio', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('FUC Comercio', 'MODULE_PAYMENT_BIZUM_ID_COM', '', 'Cod. de comercio proporcionado por la entidad bancaria', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Clave de Encriptación (SHA-256)', 'MODULE_PAYMENT_BIZUM_ID_CLAVE256', '', 'Clave de encriptación SHA-256 proporcionada por la entidad bancaria', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Terminal', 'MODULE_PAYMENT_BIZUM_TERMINAL', '', 'Terminal del comercio', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Moneda', 'MODULE_PAYMENT_BIZUM_CURRENCY', 'Euro', 'Moneda permitida', '6', '3', 'tep_cfg_select_option(array(\'Euro\', \'Dolar\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,date_added) values ('Error pago', 'MODULE_PAYMENT_BIZUM_ERROR_PAGO', 'si', 'Mantener carrito si se produce un error en el pago', '6', '4','tep_cfg_select_option(array(\'si\', \'no\'), ',  now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,date_added) values ('Log activo', 'MODULE_PAYMENT_BIZUM_LOG', 'no', 'Crear trazas de log', '6', '4','tep_cfg_select_option(array(\'si\', \'no\'), ',  now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Entorno de la pasarela de pago', 'MODULE_PAYMENT_BIZUM_ENTORNO', 'Entorno Pruebas', 'Entorno de la pasarela de pago', '6', '3', 'tep_cfg_select_option(array(\'Sis-d\', \'Entorno Pruebas\', \'Entorno Real\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar idiomas', 'MODULE_PAYMENT_BIZUM_IDIOMA', 'No', 'Activar idiomas del TPV', '6', '3', 'tep_cfg_select_option(array(\'Si\', \'No\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden de mostrado.', 'MODULE_PAYMENT_BIZUM_SORT_ORDER', '1', 'Orden de mostrado. El menor valor es mostrado antes que los mayores.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado del pedido', 'MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID', '0', 'Seleccione el estado del pedido un vez procesado', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
	}

	//Desinstalar módulo
	public function remove()
	{
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	public function keys()
	{
		return array(
			'MODULE_PAYMENT_BIZUM_STATUS',
			'MODULE_PAYMENT_BIZUM_NOMBRE',
			'MODULE_PAYMENT_BIZUM_ID_COM',
			'MODULE_PAYMENT_BIZUM_ID_CLAVE256',
			'MODULE_PAYMENT_BIZUM_TERMINAL',
			'MODULE_PAYMENT_BIZUM_CURRENCY',
			'MODULE_PAYMENT_BIZUM_ERROR_PAGO',
			'MODULE_PAYMENT_BIZUM_LOG',
			'MODULE_PAYMENT_BIZUM_ENTORNO',
			'MODULE_PAYMENT_BIZUM_IDIOMA',
			'MODULE_PAYMENT_BIZUM_SORT_ORDER',
			'MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID'
		);
	}
}
