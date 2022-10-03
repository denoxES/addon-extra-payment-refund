**Módulo para hacer pagos extra y devoluciones**

Lo primero que debemos hacer es copiar la clase a includes/classes

Luego, la incluimos en application_top.php (tanto de front-office como back-office)

Para mostrar los formularios, buscamos:

``` php
<h6><?php echo ENTRY_PAYMENT_METHOD; ?></h6>
```

y añadimos este bloque:

``` php
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
```

Luego, debemos de añadir la acción:

``` php
case 'refund-order':

    $result = order_tools::refund((int)$_GET['oID'], $_GET['amount'], $_GET['notes'], $login_id, $messageStack);
    echo '<pre>'.print_r($result, 1).'</pre>';
    die();

break;
```

Seguimos, modificando los métodos before_proccess y after_process. Añado dos módulos para que se vea como ejemplo.
*Importante, añadir:*

``` php
use util\order_tools as order_tools;
```

Por último, creamos la tabla:

``` sql
CREATE TABLE `order_tools_payment_movements` (
  `id` int(11) NOT NULL,
  `reference` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `orders_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `value` decimal(15,4) NOT NULL,
  `date_created` datetime NOT NULL,
  `admin_id` int(11) NOT NULL,
  `notes` text COLLATE utf8mb4_spanish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

ALTER TABLE `order_tools_payment_movements` ADD PRIMARY KEY (`id`);
```