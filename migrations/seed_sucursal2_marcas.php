<?php
/**
 * Seed: Ventas + Cotizaciones sucursal_id=2 + Marcas
 * Uso: php seed_sucursal2_marcas.php | mysql -u root --default-character-set=utf8mb4 ventapos
 */

echo "USE ventapos;\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

// ── MARCAS ────────────────────────────────────────────────────────────────────
$marcas = [
    'Stanley','Truper','DeWalt','Irwin','Urrea','Bellota','Wiha','Knipex','Bosch',
    'Makita','Milwaukee','Pavco','Durman','Helvex','Moen','Pedrollo','General Cable',
    'Condumex','Leviton','Bticino','Square D','Sylvania','Philips','Sherwin Williams',
    'Pintuco','Norton','Fischer','Hilti','3M','Sika','Loctite','Momentive','Tesa',
    'Fleximatic','Rain Bird','Kwikset','Yale','Master Lock','Mul-T-Lock','Purdy',
    'Cemex','Holcim','Metaldom','Ryobi','Metabo','DeVilbiss','APC','Franz Viegener',
    'Lorenzetti','Eternit','Pedrollo','Oatey','Titebond','Rubber','Fatmax',
];
$marcas = array_unique($marcas);
echo "-- MARCAS\n";
$mRows = [];
foreach ($marcas as $m) {
    $mRows[] = "(1,'" . addslashes($m) . "')";
}
echo "INSERT IGNORE INTO marcas (empresa_id, nombre) VALUES\n" . implode(",\n", $mRows) . ";\n\n";

// ── VENTAS sucursal_id=2 ──────────────────────────────────────────────────────
$startDate  = mktime(0,0,0,3,19,2026);
$daysSpan   = 62;
$clientIds  = array_merge([1], range(2, 51));
$pagoForms  = ['efectivo','tarjeta','transferencia'];

// Get product ID range from DB - we'll use a variable
echo "SET @pid_min = (SELECT MIN(producto_id) FROM productos WHERE codigo LIKE 'FER-%');\n";
echo "SET @pid_max = (SELECT MAX(producto_id) FROM productos WHERE codigo LIKE 'FER-%');\n";
echo "SET @pid_range = @pid_max - @pid_min + 1;\n\n";

// Build ventas with a stored procedure
echo "-- VENTAS sucursal 2\n";
echo "DROP PROCEDURE IF EXISTS gen_ventas_s2;\n";
echo "DELIMITER //\n";
echo "CREATE PROCEDURE gen_ventas_s2()\nBEGIN\n";
echo "  DECLARE v_day INT DEFAULT 0;\n";
echo "  DECLARE v_sale INT DEFAULT 0;\n";
echo "  DECLARE v_item INT DEFAULT 0;\n";
echo "  DECLARE v_sales_today INT;\n";
echo "  DECLARE v_items_count INT;\n";
echo "  DECLARE v_venta_id INT;\n";
echo "  DECLARE v_pid INT;\n";
echo "  DECLARE v_precio DECIMAL(12,2);\n";
echo "  DECLARE v_costo DECIMAL(12,4);\n";
echo "  DECLARE v_cant INT;\n";
echo "  DECLARE v_itbms DECIMAL(12,2);\n";
echo "  DECLARE v_total_l DECIMAL(12,2);\n";
echo "  DECLARE v_subtotal DECIMAL(12,2);\n";
echo "  DECLARE v_itbms_tot DECIMAL(12,2);\n";
echo "  DECLARE v_total DECIMAL(12,2);\n";
echo "  DECLARE v_costo_tot DECIMAL(12,2);\n";
echo "  DECLARE v_fecha DATETIME;\n";
echo "  DECLARE v_numfact VARCHAR(50);\n";
echo "  DECLARE v_cliente INT;\n";
echo "  DECLARE v_vendedor INT;\n";
echo "  DECLARE v_forma VARCHAR(20);\n";
echo "  DECLARE v_seq INT DEFAULT 1;\n";
echo "  SET v_venta_id = (SELECT IFNULL(MAX(venta_id),0) + 1 FROM ventas);\n";
echo "  WHILE v_day < 62 DO\n";
echo "    SET v_sales_today = 6 + (v_day % 9);\n";  // 6-14 sales per day
echo "    SET v_sale = 0;\n";
echo "    WHILE v_sale < v_sales_today DO\n";
echo "      SET v_fecha = DATE_ADD('2026-03-19', INTERVAL v_day DAY) + INTERVAL (28800 + MOD(v_venta_id * 1973, 43200)) SECOND;\n";
echo "      SET v_numfact = CONCAT('S2-', DATE_FORMAT(DATE_ADD('2026-03-19', INTERVAL v_day DAY),'%Y%m%d'), LPAD(v_seq,5,'0'));\n";
echo "      SET v_cliente = 1 + MOD(v_venta_id * 7, 51);\n";
echo "      SET v_vendedor = 1 + MOD(v_venta_id, 4);\n";
echo "      SET v_forma = ELT(1 + MOD(v_venta_id,3), 'efectivo','tarjeta','transferencia');\n";
echo "      SET v_subtotal = 0; SET v_itbms_tot = 0; SET v_costo_tot = 0;\n";
echo "      -- insert placeholder venta\n";
echo "      INSERT INTO ventas (venta_id,sucursal_id,empresa_id,numero_factura,cliente_id,vendedor_id,subtotal,itbms,descuento,total,costo,forma_pago,estado,fecha)\n";
echo "        VALUES (v_venta_id,2,1,v_numfact,v_cliente,v_vendedor,0,0,0,0,0,v_forma,'pagada',v_fecha);\n";
echo "      -- items\n";
echo "      SET v_items_count = 2 + MOD(v_venta_id, 5);\n";  // 2-6 items
echo "      SET v_item = 0;\n";
echo "      WHILE v_item < v_items_count DO\n";
echo "        SET v_pid = @pid_min + MOD((v_venta_id * 31 + v_item * 97), @pid_range);\n";
echo "        SET v_costo = (SELECT IFNULL(i.costo_promedio, p.costo) FROM inventario i JOIN productos p USING(producto_id) WHERE i.producto_id = v_pid AND i.deposito_id = 2 LIMIT 1);\n";
echo "        IF v_costo IS NULL OR v_costo = 0 THEN SET v_costo = 5.00; END IF;\n";
echo "        SET v_precio = ROUND(v_costo * 1.35, 2);\n";
echo "        SET v_cant = 1 + MOD(v_venta_id + v_item, 10);\n";
echo "        SET v_itbms = ROUND(v_cant * v_precio * 0.07, 2);\n";
echo "        SET v_total_l = ROUND(v_cant * v_precio + v_itbms, 2);\n";
echo "        INSERT INTO ventas_detalle (venta_id,producto_id,deposito_id,cantidad,precio,costo,itbms,descuento,total_linea)\n";
echo "          VALUES (v_venta_id, v_pid, 2, v_cant, v_precio, v_costo, v_itbms, 0, v_total_l);\n";
echo "        SET v_subtotal = v_subtotal + ROUND(v_cant * v_precio, 2);\n";
echo "        SET v_itbms_tot = v_itbms_tot + v_itbms;\n";
echo "        SET v_costo_tot = v_costo_tot + ROUND(v_cant * v_costo, 2);\n";
echo "        SET v_item = v_item + 1;\n";
echo "      END WHILE;\n";
echo "      SET v_total = ROUND(v_subtotal + v_itbms_tot, 2);\n";
echo "      UPDATE ventas SET subtotal=ROUND(v_subtotal,2), itbms=ROUND(v_itbms_tot,2), total=v_total, costo=ROUND(v_costo_tot,2) WHERE venta_id=v_venta_id;\n";
echo "      SET v_venta_id = v_venta_id + 1;\n";
echo "      SET v_seq = v_seq + 1;\n";
echo "      SET v_sale = v_sale + 1;\n";
echo "    END WHILE;\n";
echo "    SET v_day = v_day + 1;\n";
echo "  END WHILE;\n";
echo "END//\n";
echo "DELIMITER ;\n";
echo "CALL gen_ventas_s2();\n";
echo "DROP PROCEDURE gen_ventas_s2;\n\n";

// ── COTIZACIONES sucursal_id=2 ────────────────────────────────────────────────
echo "-- COTIZACIONES sucursal 2\n";
echo "DROP PROCEDURE IF EXISTS gen_cot_s2;\n";
echo "DELIMITER //\n";
echo "CREATE PROCEDURE gen_cot_s2()\nBEGIN\n";
echo "  DECLARE v_i INT DEFAULT 0;\n";
echo "  DECLARE v_cot_id INT;\n";
echo "  DECLARE v_item INT;\n";
echo "  DECLARE v_items_count INT;\n";
echo "  DECLARE v_pid INT;\n";
echo "  DECLARE v_precio DECIMAL(12,2);\n";
echo "  DECLARE v_costo DECIMAL(12,4);\n";
echo "  DECLARE v_cant INT;\n";
echo "  DECLARE v_itbms DECIMAL(12,2);\n";
echo "  DECLARE v_total_l DECIMAL(12,2);\n";
echo "  DECLARE v_subtotal DECIMAL(12,2);\n";
echo "  DECLARE v_itbms_tot DECIMAL(12,2);\n";
echo "  DECLARE v_total DECIMAL(12,2);\n";
echo "  DECLARE v_fecha DATETIME;\n";
echo "  DECLARE v_venc DATE;\n";
echo "  DECLARE v_num VARCHAR(50);\n";
echo "  DECLARE v_estado VARCHAR(20);\n";
echo "  DECLARE v_cliente INT;\n";
echo "  DECLARE v_vendedor INT;\n";
echo "  SET v_cot_id = (SELECT IFNULL(MAX(cotizacion_id),0) + 1 FROM cotizaciones);\n";
echo "  WHILE v_i < 100 DO\n";
echo "    SET v_fecha = DATE_ADD('2026-03-19', INTERVAL MOD(v_i * 13, 62) DAY) + INTERVAL (28800 + MOD(v_cot_id * 3571, 36000)) SECOND;\n";
echo "    SET v_venc = DATE(DATE_ADD(v_fecha, INTERVAL 15 DAY));\n";
echo "    SET v_num = CONCAT('C2-2026-', LPAD(v_i+1, 4, '0'));\n";
echo "    SET v_estado = ELT(1 + MOD(v_i,4), 'pendiente','aprobada','rechazada','convertida');\n";
echo "    SET v_cliente = 1 + MOD(v_cot_id * 11, 51);\n";
echo "    SET v_vendedor = 1 + MOD(v_cot_id, 4);\n";
echo "    SET v_subtotal = 0; SET v_itbms_tot = 0;\n";
echo "    INSERT INTO cotizaciones (cotizacion_id,sucursal_id,empresa_id,numero,cliente_id,vendedor_id,subtotal,itbms,descuento,total,estado,fecha_vencimiento,fecha)\n";
echo "      VALUES (v_cot_id,2,1,v_num,v_cliente,v_vendedor,0,0,0,0,v_estado,v_venc,v_fecha);\n";
echo "    SET v_items_count = 3 + MOD(v_i, 6);\n";
echo "    SET v_item = 0;\n";
echo "    WHILE v_item < v_items_count DO\n";
echo "      SET v_pid = @pid_min + MOD((v_cot_id * 53 + v_item * 71), @pid_range);\n";
echo "      SET v_costo = (SELECT IFNULL(costo,5.00) FROM productos WHERE producto_id = v_pid LIMIT 1);\n";
echo "      SET v_precio = ROUND(v_costo * 1.35, 2);\n";
echo "      SET v_cant = 1 + MOD(v_cot_id + v_item, 20);\n";
echo "      SET v_itbms = ROUND(v_cant * v_precio * 0.07, 2);\n";
echo "      SET v_total_l = ROUND(v_cant * v_precio + v_itbms, 2);\n";
echo "      INSERT INTO cotizaciones_detalle (cotizacion_id,producto_id,cantidad,precio,itbms,descuento,total_linea)\n";
echo "        VALUES (v_cot_id, v_pid, v_cant, v_precio, v_itbms, 0, v_total_l);\n";
echo "      SET v_subtotal = v_subtotal + ROUND(v_cant * v_precio, 2);\n";
echo "      SET v_itbms_tot = v_itbms_tot + v_itbms;\n";
echo "      SET v_item = v_item + 1;\n";
echo "    END WHILE;\n";
echo "    SET v_total = ROUND(v_subtotal + v_itbms_tot, 2);\n";
echo "    UPDATE cotizaciones SET subtotal=ROUND(v_subtotal,2), itbms=ROUND(v_itbms_tot,2), total=v_total WHERE cotizacion_id=v_cot_id;\n";
echo "    SET v_cot_id = v_cot_id + 1;\n";
echo "    SET v_i = v_i + 1;\n";
echo "  END WHILE;\n";
echo "END//\n";
echo "DELIMITER ;\n";
echo "CALL gen_cot_s2();\n";
echo "DROP PROCEDURE gen_cot_s2;\n\n";

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "SELECT 'Seeds sucursal 2 + marcas OK' AS resultado;\n";
echo "SELECT COUNT(*) AS total_ventas_s2  FROM ventas       WHERE sucursal_id = 2;\n";
echo "SELECT COUNT(*) AS total_cot_s2     FROM cotizaciones WHERE sucursal_id = 2;\n";
echo "SELECT COUNT(*) AS total_marcas     FROM marcas;\n";
