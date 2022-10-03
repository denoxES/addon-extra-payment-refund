
			case 'refund-order':

				$result = order_tools::refund((int)$_GET['oID'], $_GET['amount'], $_GET['notes'], $login_id, $messageStack);
				echo '<pre>'.print_r($result, 1).'</pre>';
				die();

			break;
		
				<div class="box-tbl grid" style="width: 100%;">
					<div class="box-head">
						<h6><?php echo ENTRY_PAYMENT_METHOD; ?></h6>
						<div class="clear"></div>
					</div>
					<div class="box-txt">
						<strong><?php echo ENTRY_PAYMENT_METHOD; ?></strong>: <?php echo $order->info['payment_method']; ?>

						<?php

						$order_tools = new order_tools((int)$oID);
						echo $order_tools->buttonGenerarDevolucion($order->info['payment_module']);
						echo $order_tools->buttonGenerarPagoExtra($order->info['payment_module'], $_GET['language']);
						echo $order_tools->buttonReembolsoExtra($order->info['payment_module']);

						?>

					</div>

				</div>
