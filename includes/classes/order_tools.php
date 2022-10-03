<?php

namespace util;

class order_tools
{
	private $_order_id;

	public function __construct(int $orders_id = 0)
	{
		$this->_order_id = $orders_id;
	}

	/**
	 * Retorna el formulario para
	 * generar el pago extra
	 *
	 * @author Daniel Lucia <daniel.lucia@denox.es>
	 * @param string $paymentMethod
	 * @return string
	 */
	public function buttonGenerarPagoExtra(string $paymentMethod = ''): string
	{

		global $currencies;

		$excluded = ['cod'];

		if (in_array($paymentMethod, $excluded)) {
			return '';
		}

		$response = '';
		$type = 'number';

		$redirect = tep_href_link('orders.php', sprintf('language=%s&oID=%d&action=edit', $_GET['language'], $this->_order_id));

		$order_total = $this->_getOrderTotal($this->_order_id);
		$order = new \order($this->_order_id);
		$extras = $this->_getOrderFromExtraPayment($this->_order_id);
		$init = $this->_getOrderFromPaymentMovements($this->_order_id, true);

		$init['value'] = round($init['value'], 2);

		if (!empty($extras)) {
			foreach ($extras as $extra) {
				if ($extra['exp_status'] == 1) {
					$init['value'] += $extra['amount'];
				}
			}
		}

		if ($order_total > $init['value']) {
			$init['amount'] = $order_total - $init['value'];
		}

		if ($init['amount'] > 0) {

			if ($init['value'] == 0) {
				$init['amount'] = 0;
			}

			$amount = $init['amount'];

			$response = '<p style="margin: 10px 0 0 0;font-weight: bold;background: #dbdbdb;padding: 0 7px;">Pagos extra</p>';
			$response .= '<form method="post" style="margin-top: 10px;padding: 0;display: flex;border: none;" action="' . tep_href_link('extra_payment.php', 'action=update') . '" class="formRow" id="refund-order" onsubmit="return confirm(\'¿Estás seguro?\');">';
			$response .= '<input type="' . $type . '" name="exp_amount" value="' . $amount . '" style="text-align: right; margin: 0; max-width: 100px; height: auto; margin: 0 5px 0 0;" autocomplete="off" step="any" min="0" max="' . ($amount > 0 ? $amount : '') . '" />';
			$response .= '<input name="email_address" type="hidden" value="' . $order->customer['email_address'] . '" />';
			$response .= '<input name="customers_name" type="hidden" value="' . $order->customer['name'] . '" />';
			$response .= '<input name="customers_language" type="hidden" value="' . $this->_getLanguageIdFromCustomersId((int) $order->customer['id']) . '" />';
			$response .= '<input name="redirect" type="hidden" value="' . $redirect . '" />';
			$response .= '<input name="exp_order" type="hidden" value="' . $this->_order_id . '" />';
			$response .= '<input name="customers_id" type="hidden" value="' . $order->customer['id'] . '" />';
			$response .= '<input name="notes" type="text" style="margin:0 5px 0 0;height: auto;width: 100%;" placeholder="Nota" />';
			$response .= '<button type="submit" class="buttonS bGreen" style="white-space: nowrap;">Generar pago extra</button>';
			$response .= '</form>';
		}
		if (!empty($extras)) {

			$response .= '<div style="margin-top: 10px">';

			foreach ($extras as $extra) {

				$style = 'display: flex;gap: 10px;padding: 3px 10px;justify-content: space-between;/* background-color: rgba(44, 126, 49, .2); */border: 1px solid transparent;';
				if ($extra['exp_status'] == '1') {
					$style = 'display: flex;gap: 10px;padding: 3px 10px;justify-content: space-between;background-color: rgba(44, 126, 49, .2);border: 1px solid rgba(44, 126, 49, .2);';
				}

				$response .= '<pre style="' . $style . '"><strong style="width: 100%;">' . $extra['exp_date_added'] . '</strong><span style="width: 100%;">' . $currencies->format($extra['amount']) . '</span><span style="width: 100%;">' . ($extra['notes'] == '' ? 'n/a' : $extra['notes']) . '</span>';

				$response .= '<p>';

				if ($extra['exp_status'] == '1') {
					$response .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10);
				} else {
					$response .= tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
				}

				$response .= '</p>';
				$response .= '</pre>';
			}

			$response .= '</div>';
		}

		return $response;
	}

	/**
	 * Retorna HTML para generar la devolución
	 *
	 * @author Daniel Lucia <daniel.lucia@denox.es>
	 * @param string $payment_module
	 * @return string
	 */
	public function buttonGenerarDevolucion(string $payment_module = ''): string
	{

		global $currencies;

		$allowed = ['redsys', 'bizum', 'paypal', 'redsys1', 'paypal_vzero'];

		if (empty($allowed)) {
			return '';
		}

		if ($payment_module == '') {
			return '';
		}

		if (!in_array($payment_module, $allowed)) {
			return '<p style="margin-top: 10px; color: grey; opacity: .7;">Para este pedido no está disponible la opción de devolución.</p>';
		}

		$order_total = $this->_getOrderTotal($this->_order_id);
		$order = $this->_getOrderFromPaymentMovements($this->_order_id);

		if (empty($order)) {
			return '<p style="margin-top: 10px; color: grey; opacity: .7;">Para este pedido no está disponible la opción de devolución.</p>';
		}

		$init = $this->_getOrderFromPaymentMovements($this->_order_id, true);
		$init['value'] = round($init['value'], 2);

		if ($order['amount'] == 0) {
			$response = '<p style="margin-top: 10px; color: red;">Este pedido ha sido devuelto por completo.</p>';
		} else {

			if ($init['value'] > $order_total) {
				$order['amount'] = $init['value'] - $order_total;
			}

			$response = '<p style="margin: 10px 0 0 0;font-weight: bold;background: #dbdbdb;padding: 0 7px;">Devoluciones</p>';

			$type = 'text';
			//if ($_SERVER['REMOTE_ADDR'] == '81.40.70.106') {
			$type = 'number';
			//}

			$sql = sprintf(
				'SELECT r.reference, r.value, r.admin_id, a.admin_firstname, a.admin_lastname, r.date_created, r.notes
							FROM order_tools_payment_movements r
							LEFT JOIN admin a ON (a.admin_id = r.admin_id)
							WHERE r.orders_id = %d AND r.value < 0',
				$this->_order_id
			);

			$sql = tep_db_query($sql);
			if (tep_db_num_rows($sql)) {
				while ($log = tep_db_fetch_array($sql)) {
					$order['amount'] += $log['value'];
				}
			}

			$amount = number_format(floatval($order['amount']), 2, '.', '');
			$response .= '<form method="post" style="margin-top: 10px;padding: 0;display: flex;border: none;" action="' . tep_href_link('orders.php', 'action=refund-order&oID=' . $this->_order_id) . '" class="formRow" id="refund-order">';
			$response .= '<input id="refund-order-amount" type="' . $type . '" name="amount" value="' . $amount . '" style="text-align: right; margin: 0; max-width: 100px; height: auto; margin: 0 5px 0 0;" autocomplete="off" step="any" min="0" max="' . $amount . '" />';
			$response .= '<input id="refund-order-max" type="hidden" value="' . $amount . '" />';
			$response .= '<input id="refund-order-notes" name="note" type="text" style="margin:0 5px 0 0;height: auto;width: 100%;" placeholder="Nota" />';
			$response .= '<button type="submit" class="buttonS bRed" style="white-space: nowrap;">Generar devolución</button>';
			$response .= '</form>';
		}

		if ($init['value'] > $order_total) {
			$response .= '<pre style="margin-top: 10px;border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">Inicial</strong><strong style="width: 100%;">Editado</strong><strong style="width: 100%;">Diferencia</strong></pre>';
			$response .= '<pre style="display: flex;gap: 10px;padding: 0 0 5px 0;justify-content: space-between;"><span style="width: 100%;">' . $currencies->format($init['value']) . '</span><span style="width: 100%;">' . $currencies->format($order_total) . '</span><strong style="width: 100%;">' . $currencies->format($init['value'] - $order_total) . '</strong></pre>';
		}

		$sql = sprintf(
			'SELECT r.reference, r.value, r.admin_id, a.admin_firstname, a.admin_lastname, r.date_created, r.notes
					FROM order_tools_payment_movements r
					LEFT JOIN admin a ON (a.admin_id = r.admin_id)
					WHERE r.orders_id = %d AND r.value < 0',
			$this->_order_id
		);

		$sql = tep_db_query($sql);
		if (tep_db_num_rows($sql)) {
			$response .= '<div style="margin-top: 10px">';
			$response .= '<pre style="display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;"></strong><span style="width: 100%;">Cantidad</span><span style="width: 100%;">Fecha</span><span style="width: 100%;">Notas</span></pre>';
			$response .= '<pre style="border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">Total pedido</strong><span style="width: 100%;">' . $currencies->format($init['value']) . '</span><span style="width: 100%;">' . $init['date_created'] . '</span><span style="width: 100%;"></span></pre>';
			$restante = $init['value'];
			while ($log = tep_db_fetch_array($sql)) {
				$response .= '<pre style="border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">' . $log['admin_firstname'] . ' ' . $log['admin_lastname'] . '</strong><span style="width: 100%; color: red;">' . $currencies->format($log['value']) . '</span><span style="width: 100%;">' . $log['date_created'] . '</span><span style="width: 100%;font-style: italic;font-size: 0.9em;">' . ($log['notes'] != '' ? $log['notes'] : '--') . '</span></pre>';
				$restante = $restante + $log['value'];
			}

			$response .= '<pre style="border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">Restante</strong><span style="width: 100%;">' . $currencies->format($restante) . '</span><span style="width: 100%;">--</span><span style="width: 100%;"></span></pre>';
			$response .= '</div>';
		}

		$response .= '<div id="redsys-form-content"></div>';

		$response .= '
			<script>
			document.addEventListener("DOMContentLoaded", function() {
				$("#refund-order").submit(function() {

					if (!confirm(\'¿Estás seguro?\')) {
						return false;
					}
					amount = parseFloat($("#refund-order-amount").val())
					notes = $("#refund-order-notes").val()
					max = parseFloat($("#refund-order-max").val())

					if (amount > max) {
						alert("Revise la cantidad a devolver.")
						return false
					}

					if (confirm("¿Estás seguro?")) {
						$.ajax({
							type: "GET",
							url: "' . tep_href_link('orders.php', 'action=refund-order&oID=' . $this->_order_id) . '",
							data: {
								amount: amount,
								notes: notes
							}
						}).done(function( data ) {
							location.href = location.href
							//console.log(data)
						})
					}

					return false
				})
			}, false);
			</script>
			';

		return $response;
	}

	/**
	 * Retorna los datos guardados
	 * de los pagos
	 * @author Daniel Lucia <daniel.lucia@denox.es>
	 */
	private function _getOrderFromPaymentMovements(bool $init = false): array
	{
		if ($init == true) {
			$sql = sprintf(
				'SELECT r.reference, r.value, r.admin_id, a.admin_firstname, a.admin_lastname, r.date_created
						FROM order_tools_payment_movements r
						LEFT JOIN admin a ON (a.admin_id = r.admin_id)
						WHERE r.orders_id = %d AND r.value > 0',
				$this->_order_id
			);
			$sql = tep_db_query($sql);
			if (!tep_db_num_rows($sql)) {
				return [];
			}

			return tep_db_fetch_array($sql);
		}
		$sql = sprintf(
			'SELECT reference, SUM(value) as amount
				FROM order_tools_payment_movements
				WHERE orders_id = %d
				GROUP BY orders_id',
			$this->_order_id
		);

		$sql = tep_db_query($sql);

		if (!tep_db_num_rows($sql)) {
			return [];
		}

		return tep_db_fetch_array($sql);
	}

	/**
	 * Retorna los pagos extras de un pedido
	 *
	 * @param integer $oID
	 * @return array
	 */
	private function _getOrderFromExtraPayment(int $oID = 0): array
	{
		$sql = sprintf(
			'SELECT exp_amount as amount, exp_date_added, exp_status, exp_id, notes
				FROM extra_payment
				WHERE exp_order = %d',
			$oID
		);

		$sql = tep_db_query($sql);

		if (!tep_db_num_rows($sql)) {
			return [];
		}

		$response = [];
		while ($dato = tep_db_fetch_array($sql)) {
			$response[] = $dato;
		}

		return $response;
	}

	/**
	 * Obtiene el idioma de un cliente
	 *
	 * @author Daniel Lucia <daniel.lucia@denox.es>
	 * @param integer $customers_id
	 * @return integer
	 */
	private function _getLanguageIdFromCustomersId(int $customers_id): int
	{
		$sql = sprintf('SELECT customers_language_id FROM customers WHERE customers_id = %d', $customers_id);
		$sql = tep_db_query($sql);

		if (!tep_db_num_rows($sql)) {
			return 3;
		}

		$customer = tep_db_fetch_array($sql);

		if ($customer['customers_language_id'] == 0) {
			return 3;
		}

		return (int) $customer['customers_language_id'];
	}

	/**
	 * Retorna el total de un pedido
	 *
	 * @author Daniel Lucia <daniel.lucia@denox.es>
	 * @param integer $oID
	 * @return float
	 */
	private function _getOrderTotal(int $oID = 0): float
	{
		$order_total = 0.00;
		$sql = sprintf('SELECT value FROM orders_total WHERE orders_id = %d AND class="ot_total"', $oID);

		$sql = tep_db_query($sql);
		if (tep_db_num_rows($sql)) {
			$order = tep_db_fetch_array($sql);
			$order_total = round($order['value'], 2);
		}

		return $order_total;
	}

	/**
	 * Retorna el formulario para guardar el
	 * reembolso extra
	 *
	 * @author Daniel Lucia <daniel.lucia@denox.es>
	 * @param string $paymentMethod
	 * @return string
	 */
	public function buttonReembolsoExtra(string $paymentMethod = ''): string
	{
		$excluded = ['cod'];

		if (in_array($paymentMethod, $excluded)) {
			return '';
		}

		$type = 'number';
		$sql = sprintf('SELECT reembolso_extra FROM orders WHERE orders_id = %d', $oID);
		$datos = tep_db_query($sql);
		$dato = tep_db_fetch_array($datos);
		$amount = $dato['reembolso_extra'];

		$response = '<p style="margin: 10px 0 0 0;font-weight: bold;background: #dbdbdb;padding: 0 7px;">Reembolso extra</p>';
		$response .= '<form method="post" style="margin-top: 10px;padding: 0;display: flex;border: none;" action="' . tep_href_link('orders.php', 'action=saveReembolsoExtra') . '" class="formRow" id="refund-order">';
		$response .= '<input  type="' . $type . '" name="value" value="' . $amount . '" style="text-align: right; margin: 0; max-width: 100%; height: auto; margin: 0 5px 0 0;" autocomplete="off" step="any" min="0" />';
		$response .= '<input name="order_id" type="hidden" value="' . $oID . '" />';
		$response .= '<button type="submit" class="buttonS bGreen" style="white-space: nowrap;">Guardar reembolso extra</button>';
		$response .= '</form>';

		return $response;
	}

	/**
	 * Guarda los datos de pago
	 *
	 * @param array $values
	 * @return void
	 */
	public static function before_process(string $module, array $values)
	{

		$values['date_created'] = 'now()';
		$values['module'] = tep_db_prepare_input($module);

		if (isset($_SESSION['id_extra_payment'])) {
			$values['id_extra_payment'] = (int) $_SESSION['id_extra_payment'];
		}

		tep_db_perform(
			'order_tools_payment_movements',
			$values
		);
	}

	/**
	 * Actualizamos datos de pago
	 *
	 * @param integer $order_id
	 * @param integer $customer_id
	 * @return void
	 */
	public static function after_process(int $order_id, int $customer_id)
	{
		$sql = sprintf(
			'UPDATE order_tools_payment_movements SET orders_id = %d WHERE customer_id = %d ORDER BY id DESC LIMIT 1',
			$order_id,
			$customer_id
		);
		tep_db_query($sql);
	}
}
