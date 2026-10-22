<?php
/**
 * Demo seed: Ferretería
 * Uso: php seed_demo_ferreteria.php | mysql -u root ventapos
 * Contraseña usuarios demo: Demo123!
 */

$bcrypt = '$2y$10$PD1JqUyydrQ6cWw7cI5G7OyQuna9Z.OfZskmu2TW0I2wxqSGNcgIS';

echo "USE ventapos;\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

// ── USUARIOS ──────────────────────────────────────────────────────────────────
echo "-- USUARIOS\n";
echo "INSERT INTO users (id,username,password,email,first_name,last_name,is_active,is_staff,is_superuser,rol,api_token) VALUES\n";
echo "(2,'cajero','{$bcrypt}','cajero@ferreteria.com','Carlos','Mora',1,0,0,'cajero','token_cajero_demo_2024'),\n";
echo "(3,'gerente','{$bcrypt}','gerente@ferreteria.com','Gloria','Navarro',1,1,0,'gerente','token_gerente_demo_2024'),\n";
echo "(4,'supervisor','{$bcrypt}','supervisor@ferreteria.com','Sebastian','Orozco',1,0,0,'supervisor','token_supervisor_demo_2024');\n\n";

// ── CATEGORÍAS ────────────────────────────────────────────────────────────────
echo "-- CATEGORÍAS\n";
$cats = [
    // [id, nombre, padre_id, nivel]
    [2, 'Herramientas Manuales', 'NULL', 1],
    [3, 'Herramientas Eléctricas', 'NULL', 1],
    [4, 'Plomería', 'NULL', 1],
    [5, 'Electricidad', 'NULL', 1],
    [6, 'Pintura y Acabados', 'NULL', 1],
    [7, 'Materiales de Construcción', 'NULL', 1],
    [8, 'Tornillería y Fijaciones', 'NULL', 1],
    [9, 'Seguridad Industrial', 'NULL', 1],
    [10, 'Adhesivos y Selladores', 'NULL', 1],
    [11, 'Jardín y Riego', 'NULL', 1],
    [12, 'Cerrajería', 'NULL', 1],
    [13, 'Abrasivos y Corte', 'NULL', 1],
    // Herramientas Manuales
    [14, 'Martillos y Mazos', 2, 2],
    [15, 'Destornilladores', 2, 2],
    [16, 'Llaves y Torquímetros', 2, 2],
    [17, 'Alicates y Pinzas', 2, 2],
    [18, 'Sierras Manuales', 2, 2],
    [19, 'Medición y Trazado', 2, 2],
    [20, 'Cinceles y Formones', 2, 2],
    // Herramientas Eléctricas
    [21, 'Taladros', 3, 2],
    [22, 'Sierras Eléctricas', 3, 2],
    [23, 'Amoladoras', 3, 2],
    [24, 'Lijadoras', 3, 2],
    [25, 'Rotomartillos', 3, 2],
    // Plomería
    [26, 'Tubería PVC', 4, 2],
    [27, 'Conexiones PVC', 4, 2],
    [28, 'Grifería', 4, 2],
    [29, 'Bombas de Agua', 4, 2],
    [30, 'Accesorios Sanitarios', 4, 2],
    // Electricidad
    [31, 'Cables y Conductores', 5, 2],
    [32, 'Tomacorrientes e Interruptores', 5, 2],
    [33, 'Iluminación', 5, 2],
    [34, 'Protección Eléctrica', 5, 2],
    // Pintura
    [35, 'Pinturas', 6, 2],
    [36, 'Herramientas de Pintura', 6, 2],
    [37, 'Disolventes y Diluyentes', 6, 2],
    // Construcción
    [38, 'Cemento y Mezclas', 7, 2],
    [39, 'Varillas y Perfiles', 7, 2],
    [40, 'Mallas y Alambre', 7, 2],
    // Tornillería
    [41, 'Tornillos', 8, 2],
    [42, 'Tuercas y Arandelas', 8, 2],
    [43, 'Clavos', 8, 2],
    [44, 'Anclajes y Tacos', 8, 2],
    // Seguridad
    [45, 'Protección Personal', 9, 2],
    [46, 'Señalización', 9, 2],
    // Adhesivos
    [47, 'Siliconas', 10, 2],
    [48, 'Pegamentos', 10, 2],
    [49, 'Cintas Adhesivas', 10, 2],
    // Jardín
    [50, 'Herramientas de Jardín', 11, 2],
    [51, 'Mangueras y Riego', 11, 2],
    // Cerrajería
    [52, 'Candados', 12, 2],
    [53, 'Cerraduras', 12, 2],
    [54, 'Bisagras y Herrajes', 12, 2],
    // Abrasivos
    [55, 'Discos de Corte y Desbaste', 13, 2],
    [56, 'Lijas y Abrasivos', 13, 2],
    [57, 'Brocas', 13, 2],
];

$rows = [];
foreach ($cats as $c) {
    $rows[] = "({$c[0]},1,'" . addslashes($c[1]) . "',{$c[2]},{$c[3]})";
}
echo "INSERT INTO categorias_productos (categoria_id,empresa_id,nombre,padre_id,nivel) VALUES\n";
echo implode(",\n", $rows) . ";\n\n";

// ── PROVEEDORES ───────────────────────────────────────────────────────────────
echo "-- PROVEEDORES\n";
$provs = [
    [1,'PROV01','Distribuidora Nacional de Herramientas S.A.','155-001','Roberto Arias','507-6001-0001','ventas@distherr.com'],
    [2,'PROV02','Plomería y Construcción del Pacífico S.A.','155-002','Ana Vásquez','507-6002-0002','ventas@plomcon.com'],
    [3,'PROV03','Materiales Eléctricos Centroamérica S.A.','155-003','Miguel Torres','507-6003-0003','ventas@electrocen.com'],
    [4,'PROV04','Importadora de Pinturas y Acabados S.A.','155-004','Laura Méndez','507-6004-0004','ventas@pintacab.com'],
    [5,'PROV05','Ferretería Mayorista Central S.A.','155-005','David Castillo','507-6005-0005','ventas@ferrmay.com'],
    [6,'PROV06','Seguridad Industrial Panamá S.A.','155-006','Carmen López','507-6006-0006','ventas@segind.com'],
    [7,'PROV07','Herraferr Importaciones S.A.','155-007','Jorge Morales','507-6007-0007','ventas@herraferr.com'],
    [8,'PROV08','Cemento y Materiales de Panamá S.A.','155-008','Patricia Núñez','507-6008-0008','ventas@cempan.com'],
];
echo "INSERT INTO proveedores (proveedor_id,empresa_id,codigo,nombre,ruc,contacto,telefono,email) VALUES\n";
$rows = [];
foreach ($provs as $p) {
    $rows[] = "({$p[0]},1,'{$p[1]}','" . addslashes($p[2]) . "','{$p[3]}','" . addslashes($p[4]) . "','{$p[5]}','{$p[6]}')";
}
echo implode(",\n", $rows) . ";\n\n";

// ── PRODUCTOS ─────────────────────────────────────────────────────────────────
// Templates: [cat_id, nombre_base, marca, costo, proveedor_id, variantes[]]
$templates = [
    // Martillos y Mazos (14)
    [14,'Martillo de Carpintero','Stanley',8.50,1,['8 oz','12 oz','16 oz','20 oz']],
    [14,'Martillo de Carpintero','Truper',7.80,1,['12 oz','16 oz','20 oz']],
    [14,'Martillo de Bola','DeWalt',15.20,1,['16 oz','20 oz','24 oz']],
    [14,'Martillo de Bola','Stanley',12.50,1,['16 oz','20 oz']],
    [14,'Mazo de Goma','Irwin',9.75,1,['2 lb','3 lb','4 lb']],
    [14,'Mazo de Goma','Truper',8.00,1,['2 lb','3 lb','4 lb']],
    [14,'Martillo Demoledor','Urrea',18.00,1,['2 lb','3 lb','4 lb','5 lb']],
    [14,'Martillo de Tapicero','Stanley',11.50,1,['7 oz','10 oz']],
    [14,'Mazo de Cuero','Bellota',22.00,1,['250g','500g']],
    [14,'Martillo de Geólogo','Irwin',25.00,1,['14 oz','22 oz']],

    // Destornilladores (15)
    [15,'Destornillador Plano','Stanley',3.50,1,['1/8"','3/16"','1/4"','5/16"']],
    [15,'Destornillador Phillips','Stanley',3.75,1,['#1','#2','#3']],
    [15,'Destornillador Plano','Truper',2.80,1,['1/8"','3/16"','1/4"']],
    [15,'Destornillador Phillips','Truper',2.90,1,['#1','#2','#3']],
    [15,'Destornillador Torx','Wiha',7.50,1,['T10','T15','T20','T25','T30']],
    [15,'Destornillador de Impacto','DeWalt',12.00,1,['#2','#3']],
    [15,'Set Destornilladores','Stanley',18.50,1,['6 pzas','8 pzas','10 pzas']],
    [15,'Set Destornilladores','Truper',14.00,1,['6 pzas','8 pzas']],
    [15,'Destornillador Magnético','Wiha',8.00,1,['#2 Phillips','#3 Phillips','1/4" Plano']],

    // Llaves (16)
    [16,'Llave Ajustable','Stanley',12.50,1,['6"','8"','10"','12"']],
    [16,'Llave Ajustable','Truper',10.00,1,['6"','8"','10"','12"']],
    [16,'Llave de Corona','Urrea',6.50,1,['10mm','12mm','13mm','14mm','17mm','19mm']],
    [16,'Llave Española','Urrea',5.25,1,['10mm','12mm','13mm','14mm','17mm','19mm']],
    [16,'Set de Llaves Allen Hexagonal','Stanley',15.00,1,['SAE 9 pzas','Métrico 9 pzas']],
    [16,'Set de Llaves Allen Hexagonal','Truper',12.00,1,['SAE 9 pzas','Métrico 9 pzas']],
    [16,'Set Llaves Combinadas','Urrea',45.00,1,['8-22mm','10-19mm']],
    [16,'Torquímetro','Irwin',65.00,1,['3/8" 5-80 Nm','1/2" 10-150 Nm']],
    [16,'Llave de Tubo','Truper',14.00,1,['14"','18"','24"']],
    [16,'Llave de Cadena','Urrea',22.00,1,['1"','2"']],

    // Alicates (17)
    [17,'Alicate de Presión','Knipex',18.50,1,['7"','10"']],
    [17,'Alicate de Punta','Stanley',9.75,1,['6"','8"']],
    [17,'Alicate de Corte Diagonal','Knipex',14.00,1,['6"','7"','8"']],
    [17,'Alicate Universal','Truper',8.50,1,['6"','8"']],
    [17,'Alicate de Pico de Loro','Irwin',12.00,1,['10"','12"']],
    [17,'Pinza de Electricista','Knipex',16.00,1,['7"','8"']],
    [17,'Alicate para Anillos','Truper',9.50,1,['Externo 7"','Interno 7"']],

    // Sierras Manuales (18)
    [18,'Serrucho de Carpintero','Stanley',14.00,1,['20"','22"','26"']],
    [18,'Serrucho de Carpintero','Truper',11.00,1,['20"','22"']],
    [18,'Sierra Hacksaw','Stanley',12.50,1,['12"']],
    [18,'Sierra Hacksaw','Irwin',10.00,1,['12"']],
    [18,'Segueta para Hacksaw','Stanley',3.50,1,['18T','24T','32T']],
    [18,'Sierra Caladora Manual','Bellota',25.00,1,['8"','10"']],
    [18,'Sierra de Arco Ajustable','Stanley',16.00,1,['24"']],

    // Medición (19)
    [19,'Cinta Métrica','Stanley',8.50,1,['3m','5m','8m','10m']],
    [19,'Cinta Métrica','Truper',6.50,1,['3m','5m','8m']],
    [19,'Nivel de Burbuja','Stanley',14.00,1,['24"','48"']],
    [19,'Nivel de Burbuja','Truper',10.00,1,['24"','48"']],
    [19,'Nivel Láser','DeWalt',85.00,1,['Línea','Punto Cruz']],
    [19,'Plomada','Stanley',7.50,1,['8 oz','16 oz']],
    [19,'Escuadra de Carpintero','Irwin',9.00,1,['6"','12"']],
    [19,'Transportador','Stanley',4.50,1,['6"','12"']],
    [19,'Compás de Puntas','Urrea',8.00,1,['6"','8"']],

    // Cinceles (20)
    [20,'Cincel Plano','Stanley',6.50,1,['1/2"','3/4"','1"']],
    [20,'Cincel Plano','Truper',5.00,1,['1/2"','3/4"','1"']],
    [20,'Cincel de Punta','Stanley',7.00,1,['1/2"','3/4"']],
    [20,'Formón de Carpintero','Irwin',9.50,1,['1/4"','1/2"','3/4"','1"']],
    [20,'Cincel para Mampostería','Truper',5.50,1,['1/2"','3/4"','1"']],

    // Taladros (21)
    [21,'Taladro Percutor','Bosch',75.00,7,['500W','700W']],
    [21,'Taladro Percutor','Makita',80.00,7,['500W','700W']],
    [21,'Taladro Inalámbrico','DeWalt',120.00,7,['18V','20V']],
    [21,'Taladro Inalámbrico','Bosch',115.00,7,['12V','18V']],
    [21,'Taladro de Banco','Truper',95.00,7,['1/2 HP','3/4 HP']],
    [21,'Taladro Atornillador','Makita',85.00,7,['12V','18V']],
    [21,'Pistola de Silicona Inalámbrica','Bosch',45.00,7,['12V']],

    // Sierras Eléctricas (22)
    [22,'Sierra Circular','DeWalt',145.00,7,['6-1/2"','7-1/4"']],
    [22,'Sierra Circular','Makita',135.00,7,['6-1/2"','7-1/4"']],
    [22,'Sierra de Caladora','Bosch',95.00,7,['350W','500W']],
    [22,'Sierra de Caladora','DeWalt',105.00,7,['350W','500W']],
    [22,'Sierra Ingletadora','DeWalt',220.00,7,['10"','12"']],
    [22,'Sierra Sable','Milwaukee',125.00,7,['1100W','1200W']],
    [22,'Sierra de Banda','Makita',165.00,7,['10"']],

    // Amoladoras (23)
    [23,'Amoladora Angular','Bosch',65.00,7,['4-1/2" 720W','5" 800W','7" 1400W']],
    [23,'Amoladora Angular','Makita',70.00,7,['4-1/2" 720W','5" 840W']],
    [23,'Amoladora Angular','DeWalt',75.00,7,['4-1/2" 800W','7" 1400W']],
    [23,'Amoladora de Banco','Truper',55.00,7,['6" 1/2 HP','8" 3/4 HP']],

    // Lijadoras (24)
    [24,'Lijadora Orbital','Bosch',75.00,7,['1/4 Hoja','1/2 Hoja']],
    [24,'Lijadora Orbital','Makita',80.00,7,['1/4 Hoja','1/2 Hoja']],
    [24,'Lijadora de Banda','DeWalt',95.00,7,['3"x18"','3"x21"']],
    [24,'Lijadora de Detalle','Bosch',55.00,7,['120W']],

    // Rotomartillos (25)
    [25,'Rotomartillo SDS-Plus','Bosch',150.00,7,['2J','3J']],
    [25,'Rotomartillo SDS-Plus','Makita',145.00,7,['2J','3J']],
    [25,'Rotomartillo SDS-Max','DeWalt',280.00,7,['5J','8J']],
    [25,'Rotomartillo SDS-Plus','Milwaukee',165.00,7,['2J','3J']],

    // Tubería PVC (26)
    [26,'Tubo PVC Presión','Pavco',4.50,2,['1/2" x 6m','3/4" x 6m','1" x 6m','2" x 6m']],
    [26,'Tubo PVC Sanitario','Pavco',5.00,2,['2" x 6m','3" x 6m','4" x 6m']],
    [26,'Tubo PVC Conduit','Durman',3.75,2,['1/2" x 3m','3/4" x 3m','1" x 3m']],
    [26,'Tubo CPVC','Durman',8.50,2,['1/2" x 6m','3/4" x 6m','1" x 6m']],

    // Conexiones PVC (27)
    [27,'Codo PVC 90°','Pavco',0.85,2,['1/2"','3/4"','1"','2"','3"','4"']],
    [27,'Codo PVC 45°','Pavco',0.90,2,['1/2"','3/4"','1"','2"','3"']],
    [27,'Tee PVC','Pavco',1.10,2,['1/2"','3/4"','1"','2"','3"','4"']],
    [27,'Unión PVC','Pavco',0.65,2,['1/2"','3/4"','1"','2"','3"']],
    [27,'Reducción PVC','Durman',0.75,2,['3/4" a 1/2"','1" a 3/4"','2" a 1"']],
    [27,'Tapón PVC','Pavco',0.45,2,['1/2"','3/4"','1"','2"']],
    [27,'Adaptador Macho PVC','Durman',0.55,2,['1/2"','3/4"','1"','2"']],

    // Grifería (28)
    [28,'Llave de Paso PVC','Pavco',3.50,2,['1/2"','3/4"','1"','2"']],
    [28,'Llave de Paso Bronce','Helvex',12.50,2,['1/2"','3/4"','1"']],
    [28,'Grifo de Lavatorio','Helvex',25.00,2,['Cromado','Dorado']],
    [28,'Grifo de Cocina','Moen',45.00,2,['Monomando','Dos mandos']],
    [28,'Grifo de Ducha','Moen',55.00,2,['Monomando','Empotrado']],
    [28,'Válvula de Flotador','Truper',8.00,2,['1/2"','3/4"']],
    [28,'Válvula Check','Pavco',9.50,2,['1/2"','3/4"','1"','2"']],

    // Bombas de Agua (29)
    [29,'Bomba Periférica','Pedrollo',85.00,2,['1/2 HP','1 HP']],
    [29,'Bomba Centrífuga','Truper',95.00,2,['1/2 HP','1 HP','2 HP']],
    [29,'Bomba Sumergible','Pedrollo',145.00,2,['1/2 HP','1 HP']],
    [29,'Hidroneumático','Pedrollo',220.00,2,['1 HP','2 HP']],

    // Accesorios Sanitarios (30)
    [30,'Inodoro Económico','Franz Viegener',85.00,2,['Blanco']],
    [30,'Lavatorio Pedestal','Franz Viegener',75.00,2,['Blanco','Marfil']],
    [30,'Ducha Eléctrica','Lorenzetti',35.00,2,['4500W','5500W']],
    [30,'Tanque Reservorio','Eternit',55.00,2,['250L','500L','1000L']],
    [30,'Fluxómetro','Helvex',28.00,2,['1/2"','3/4"']],
    [30,'Sello de Wax para Inodoro','Truper',2.50,2,['Estándar']],

    // Cables y Conductores (31)
    [31,'Cable THW #12','General Cable',0.85,3,['x metro','x 100m rollo']],
    [31,'Cable THW #10','General Cable',1.25,3,['x metro','x 100m rollo']],
    [31,'Cable THW #8','General Cable',1.85,3,['x metro','x 100m rollo']],
    [31,'Cable THHN #12','Condumex',0.90,3,['x metro','x 100m rollo']],
    [31,'Cable THHN #10','Condumex',1.30,3,['x metro','x 100m rollo']],
    [31,'Cable de Acometida 2x10','Condumex',2.50,3,['x metro']],
    [31,'Cable de Acometida 2x8','Condumex',3.50,3,['x metro']],
    [31,'Alambre Galvanizado #18','Truper',0.25,3,['x kg','x rollo 1kg','x rollo 5kg']],

    // Tomacorrientes e Interruptores (32)
    [32,'Tomacorriente Doble','Leviton',3.50,3,['Blanco','Marfil']],
    [32,'Tomacorriente Doble','Bticino',4.50,3,['Blanco','Marfil']],
    [32,'Tomacorriente GFCI','Leviton',12.00,3,['Blanco']],
    [32,'Interruptor Simple','Leviton',2.50,3,['Blanco','Marfil']],
    [32,'Interruptor Doble','Leviton',4.00,3,['Blanco','Marfil']],
    [32,'Interruptor Doble','Bticino',5.50,3,['Blanco']],
    [32,'Placa para Tomacorriente','Leviton',1.25,3,['Blanco','Marfil','Aluminio']],
    [32,'Caja de Salida 4x4','Solida',2.00,3,['Galvanizada']],
    [32,'Caja de Salida 2x4','Solida',1.50,3,['Galvanizada']],

    // Iluminación (33)
    [33,'Bombillo LED','Sylvania',3.50,3,['7W','9W','12W','15W']],
    [33,'Bombillo LED','Philips',4.25,3,['7W','9W','12W','15W']],
    [33,'Panel LED Redondo','Sylvania',8.50,3,['9W','12W','18W']],
    [33,'Tubo LED T8','Sylvania',6.50,3,['9W 60cm','18W 120cm']],
    [33,'Reflector LED','Philips',18.00,3,['30W','50W','100W']],
    [33,'Luminaria Industrial','Sylvania',45.00,3,['100W','150W','200W']],
    [33,'Foco PAR 38','Sylvania',9.00,3,['12W E27','16W E27']],
    [33,'Tira LED','Sylvania',12.00,3,['5m 10W','5m 24W']],

    // Protección Eléctrica (34)
    [34,'Breaker 1x15A','Square D',8.50,3,['120V']],
    [34,'Breaker 1x20A','Square D',8.50,3,['120V']],
    [34,'Breaker 2x15A','Square D',14.00,3,['240V']],
    [34,'Breaker 2x20A','Square D',14.00,3,['240V']],
    [34,'Breaker 2x30A','Square D',16.00,3,['240V']],
    [34,'Tablero 4 Circuitos','Square D',35.00,3,['Interior','Exterior']],
    [34,'Tablero 8 Circuitos','Square D',55.00,3,['Interior','Exterior']],
    [34,'Tablero 12 Circuitos','Square D',75.00,3,['Interior','Exterior']],
    [34,'UPS 400VA','APC',45.00,3,['400VA','600VA']],

    // Pinturas (35)
    [35,'Pintura Látex Interior','Sherwin Williams',18.50,4,['Blanco 1g','Blanco 4g','Blanco 19L']],
    [35,'Pintura Látex Interior','Pintuco',15.00,4,['Blanco 1g','Blanco 4g']],
    [35,'Pintura Látex Exterior','Sherwin Williams',22.00,4,['Blanco 1g','Blanco 4g','Blanco 19L']],
    [35,'Pintura Anticorrosiva','Pintuco',19.00,4,['Rojo 1g','Gris 1g','Negro 1g']],
    [35,'Pintura Esmalte','Sherwin Williams',20.00,4,['Blanco 1/4 g','Blanco 1g','Negro 1g']],
    [35,'Pintura de Caucho','Pintuco',14.00,4,['Blanco 1g','Blanco 4g']],
    [35,'Pintura Epóxica','Sherwin Williams',35.00,4,['Gris 1g','Beige 1g']],
    [35,'Pintura para Piso','Pintuco',16.00,4,['Gris 1g','Rojo 1g']],
    [35,'Imprimante','Sherwin Williams',10.00,4,['Blanco 1g','Blanco 4g']],

    // Herramientas de Pintura (36)
    [36,'Brocha','Purdy',4.50,4,['1"','2"','3"','4"']],
    [36,'Brocha','Truper',2.50,4,['1"','2"','3"','4"']],
    [36,'Rodillo','Purdy',6.50,4,['7"','9"','14"']],
    [36,'Rodillo','Truper',3.50,4,['7"','9"']],
    [36,'Bandeja para Rodillo','Truper',2.25,4,['9"','14"']],
    [36,'Pistola de Pintura','DeVilbiss',75.00,4,['1 HP','2 HP']],
    [36,'Extensión para Rodillo','Truper',5.50,4,['Aluminio 1m','Fibra 2m']],
    [36,'Espátula Metálica','Stanley',3.00,4,['1"','2"','3"','4"']],

    // Disolventes (37)
    [37,'Thinner Acrílico','Pintuco',4.50,4,['1g','4g']],
    [37,'Thinner Poliuretano','Sherwin Williams',6.00,4,['1g','4g']],
    [37,'Aguarrás','Pintuco',3.50,4,['1g','4g']],
    [37,'Removedor de Pintura','Pintuco',8.50,4,['1g']],
    [37,'Sellador de Madera','Sherwin Williams',12.00,4,['1g','4g']],

    // Cemento y Mezclas (38)
    [38,'Cemento Portland','Cemex',9.50,8,['42.5kg saco']],
    [38,'Cemento Portland','Holcim',9.25,8,['42.5kg saco']],
    [38,'Mezcla para Pega de Piso','Sika',8.00,8,['20kg saco','40kg saco']],
    [38,'Mezcla para Repello','Sika',6.50,8,['20kg saco']],
    [38,'Mortero Seco','Sika',7.50,8,['20kg saco']],
    [38,'Adhesivo Cerámico','Sika',9.00,8,['20kg saco']],
    [38,'Masilla para Juntas','Sika',5.50,8,['1kg','5kg']],
    [38,'Impermeabilizante Acrílico','Sika',14.00,8,['1g','4g']],
    [38,'Fragua para Cerámica','Sika',4.50,8,['1kg','2kg','5kg']],

    // Varillas y Perfiles (39)
    [39,'Varilla de Hierro Corrugado','Metaldom',8.50,8,['3/8" x 6m','1/2" x 6m','5/8" x 6m']],
    [39,'Ángulo de Hierro','Metaldom',12.00,8,['1"x1"x6m','1.5"x1.5"x6m','2"x2"x6m']],
    [39,'Canal C de Hierro','Metaldom',15.00,8,['2"x6m','4"x6m','6"x6m']],
    [39,'Tubo Cuadrado','Metaldom',14.00,8,['1"x1"x6m','2"x2"x6m']],
    [39,'Tubo Rectangular','Metaldom',16.00,8,['1"x2"x6m','2"x4"x6m']],
    [39,'Platina de Hierro','Metaldom',10.00,8,['1/8"x1"x6m','1/4"x1"x6m']],
    [39,'Hierro Plano','Metaldom',11.00,8,['1/4"x2"x6m','1/4"x4"x6m']],

    // Mallas y Alambre (40)
    [40,'Malla Electrosoldada','Metaldom',18.50,8,['6"x6"x2.4m','4"x4"x2.4m']],
    [40,'Malla Ciclón','Metaldom',25.00,8,['2"x1m x 25m','2"x1.5m x 25m','2"x2m x 25m']],
    [40,'Alambre de Púas','Metaldom',12.00,8,['400m rollo']],
    [40,'Alambre Negro #16','Metaldom',0.35,8,['x kg','rollo 1kg','rollo 5kg']],

    // Tornillos (41)
    [41,'Tornillo Punta de Broca','Stanley',0.08,5,['6x1"','6x1.5"','8x1"','8x1.5"','8x2"','10x2"']],
    [41,'Tornillo para Madera','Truper',0.06,5,['#6x1"','#8x1.5"','#10x2"','#12x3"']],
    [41,'Tornillo para Metal','Truper',0.09,5,['M4x12','M5x16','M6x20','M8x25']],
    [41,'Tornillo de Máquina','Urrea',0.07,5,['M5x20','M6x25','M8x30','M10x40']],
    [41,'Tornillo Autoperforante','Stanley',0.10,5,['#8x1/2"','#10x3/4"','#12x1"']],
    [41,'Perno Hex Galvanizado','Urrea',0.15,5,['1/4"x1"','5/16"x1"','3/8"x1.5"','1/2"x2"']],

    // Tuercas y Arandelas (42)
    [42,'Tuerca Hexagonal','Urrea',0.05,5,['M5','M6','M8','M10','M12']],
    [42,'Tuerca Hexagonal Galvanizada','Truper',0.06,5,['1/4"','5/16"','3/8"','1/2"']],
    [42,'Arandela Plana','Urrea',0.04,5,['M5','M6','M8','M10','M12']],
    [42,'Arandela de Presión','Urrea',0.05,5,['M5','M6','M8','M10']],
    [42,'Contratuerca','Urrea',0.06,5,['M6','M8','M10','M12']],

    // Clavos (43)
    [43,'Clavo de Acero','Truper',0.02,5,['1"','1.5"','2"','2.5"','3"','3.5"','4"']],
    [43,'Clavo para Concreto','Truper',0.05,5,['1"','1.5"','2"','2.5"','3"']],
    [43,'Clavo para Techo','Truper',0.03,5,['1.5"','2"','2.5"']],
    [43,'Grapa para Cable','Stanley',0.04,5,['1/4"','3/8"','1/2"']],

    // Anclajes (44)
    [44,'Taco Expansivo Fisher','Fischer',0.35,5,['S6','S8','S10','S12']],
    [44,'Taco de Plástico','Truper',0.08,5,['6mm','8mm','10mm','12mm']],
    [44,'Ancla Química','Fischer',8.50,5,['300ml']],
    [44,'Perno de Expansión','Hilti',1.25,5,['M8x80','M10x100','M12x120']],
    [44,'Tornillo Hammer','Hilti',0.85,5,['6x40','8x60','10x80']],

    // Protección Personal (45)
    [45,'Casco de Seguridad','3M',12.00,6,['Blanco','Amarillo','Naranja']],
    [45,'Guantes de Cuero','Truper',4.50,6,['S','M','L','XL']],
    [45,'Guantes de Nitrilo','3M',0.45,6,['S','M','L']],
    [45,'Lentes de Seguridad','3M',3.50,6,['Claro','Oscuro','Gris']],
    [45,'Mascarilla KN95','3M',1.25,6,['Unidad','Caja 10 uds','Caja 50 uds']],
    [45,'Mascarilla para Polvo','3M',2.50,6,['N95 Unidad','N95 Caja 10']],
    [45,'Tapones para Oídos','3M',0.85,6,['Par','Caja 50 pares']],
    [45,'Botas de Hule','Rubber',18.00,6,['38','39','40','41','42','43','44','45']],
    [45,'Chaleco Reflectivo','Truper',5.50,6,['S','M','L','XL']],
    [45,'Rodilleras','Stanley',8.50,6,['Talla Única']],

    // Señalización (46)
    [46,'Cinta de Peligro','3M',4.50,6,['50m Amarilla','50m Roja']],
    [46,'Señal de Seguridad','Truper',3.50,6,['Salida Emergencia','No Fumar','Extintor','Cuidado Piso']],
    [46,'Cono de Seguridad','Truper',8.50,6,['18" Naranja','28" Naranja']],

    // Siliconas (47)
    [47,'Silicona Neutral','Sika',3.50,5,['Blanco 280ml','Transparente 280ml','Negro 280ml']],
    [47,'Silicona Ácida','Sika',3.00,5,['Blanco 280ml','Transparente 280ml']],
    [47,'Silicona para Sanitario','Momentive',4.50,5,['Blanco 280ml','Beige 280ml']],
    [47,'Silicona de Alta Temperatura','Momentive',5.50,5,['Rojo 280ml','Negro 280ml']],

    // Pegamentos (48)
    [48,'Pegamento PVC','Oatey',3.50,5,['Lata 118ml','Lata 473ml','Lata 946ml']],
    [48,'Pegamento de Contacto','Tesa',4.00,5,['80ml','250ml','500ml']],
    [48,'Pegamento Epóxico','Loctite',6.50,5,['Bicomponente 2x25ml','Bicomponente 2x50ml']],
    [48,'Pegamento Super Bonder','Loctite',2.50,5,['3g','20g']],
    [48,'Pegamento para Madera','Titebond',4.50,5,['118ml','473ml']],

    // Cintas Adhesivas (49)
    [49,'Cinta Masking','3M',2.50,5,['3/4" x 50m','1" x 50m','2" x 50m']],
    [49,'Cinta Duct','3M',4.50,5,['2" x 25m Gris','2" x 25m Negro']],
    [49,'Cinta Eléctrica','3M',1.50,5,['3/4" x 20m Negro','3/4" x 20m Rojo']],
    [49,'Cinta Doble Faz','3M',3.50,5,['1" x 10m','2" x 10m']],
    [49,'Cinta de Teflón','Truper',0.85,5,['1/2" x 10m']],

    // Herramientas de Jardín (50)
    [50,'Pala Puntona','Truper',14.00,1,['Sin mango','Con mango madera','Con mango fibra']],
    [50,'Pala Cuadrada','Truper',13.50,1,['Sin mango','Con mango madera']],
    [50,'Azadón','Truper',12.00,1,['Sin mango','Con mango madera']],
    [50,'Rastrillo','Truper',10.00,1,['12 dientes','16 dientes']],
    [50,'Podadera','Bellota',18.50,1,['6"','8"']],
    [50,'Machete','Truper',8.50,1,['18"','21"','24"']],
    [50,'Carretilla','Truper',55.00,1,['4 pies³','6 pies³']],
    [50,'Regadera','Truper',7.50,1,['5L','8L','10L']],

    // Mangueras y Riego (51)
    [51,'Manguera de Jardín','Fleximatic',0.85,2,['1/2" x metro','5/8" x metro']],
    [51,'Rollo Manguera de Jardín','Fleximatic',15.00,2,['1/2" x 20m','5/8" x 20m','5/8" x 30m']],
    [51,'Pistola para Manguera','Truper',6.50,2,['8 funciones','12 funciones']],
    [51,'Aspersor de Impacto','Rain Bird',8.50,2,['1/2"','3/4"']],
    [51,'Conector de Manguera','Truper',1.50,2,['1/2"','3/4"']],
    [51,'Llave de Paso para Manguera','Truper',2.50,2,['1/2"','3/4"']],

    // Candados (52)
    [52,'Candado de Latón','Truper',6.50,5,['30mm','40mm','50mm','60mm']],
    [52,'Candado de Latón','Yale',9.50,5,['30mm','40mm','50mm']],
    [52,'Candado de Combinación','Master Lock',12.00,5,['30mm','40mm']],
    [52,'Candado de Alta Seguridad','Mul-T-Lock',45.00,5,['40mm','50mm']],

    // Cerraduras (53)
    [53,'Cerradura de Pomo','Kwikset',18.00,5,['Entrada','Baño','Recámara']],
    [53,'Cerradura de Palanca','Kwikset',22.00,5,['Entrada','Baño']],
    [53,'Cerradura de Palanca','Yale',25.00,5,['Entrada','Baño']],
    [53,'Cerradura de Sobreponer','Master Lock',35.00,5,['60mm']],
    [53,'Cerradura de Embutir','Mul-T-Lock',75.00,5,['Izquierda','Derecha']],

    // Bisagras y Herrajes (54)
    [54,'Bisagra de Puerta','Stanley',1.25,5,['3x3" Cromada','3.5x3.5" Cromada','4x4" Cromada']],
    [54,'Bisagra de Piano','Truper',8.50,5,['1m','1.8m']],
    [54,'Pasador de Seguridad','Stanley',3.50,5,['4" Cromado','6" Cromado']],
    [54,'Manija de Puerta','Truper',4.50,5,['Cromado','Dorado','Satinado']],
    [54,'Portón Corredizo Kit','Stanley',45.00,5,['100kg','200kg']],

    // Discos de Corte (55)
    [55,'Disco de Corte para Metal','Norton',2.50,7,['4.5"x1mm','7"x1.6mm','9"x1.6mm']],
    [55,'Disco de Desbaste para Metal','Norton',3.50,7,['4.5"x6mm','7"x6mm']],
    [55,'Disco de Corte para Concreto','Bosch',4.50,7,['4.5"','7"','9"']],
    [55,'Disco Flap','Norton',4.00,7,['4.5" G40','4.5" G60','4.5" G80']],
    [55,'Disco de Sierra Circular','DeWalt',15.00,7,['6.5" 40D','7.25" 40D','7.25" 60D']],

    // Lijas (56)
    [56,'Lija en Hoja','Norton',0.45,7,['G40','G60','G80','G100','G120','G150','G180','G220']],
    [56,'Lija en Rollo','Norton',8.50,7,['G60 5m','G80 5m','G120 5m']],
    [56,'Lija de Agua','Norton',0.85,7,['G220','G320','G400','G600']],
    [56,'Esponja Abrasiva','3M',1.25,7,['Fina','Media','Gruesa']],

    // Brocas (57)
    [57,'Broca para Metal HSS','Bosch',1.50,7,['3mm','4mm','5mm','6mm','8mm','10mm','12mm']],
    [57,'Broca para Concreto SDS','Bosch',3.50,7,['6mm','8mm','10mm','12mm','14mm']],
    [57,'Broca para Madera','Bosch',2.00,7,['6mm','8mm','10mm','12mm','16mm','20mm']],
    [57,'Set Brocas para Metal','Stanley',18.50,7,['13 pzas','19 pzas']],
    [57,'Set Brocas Mixto','Bosch',25.00,7,['21 pzas','34 pzas']],
    [57,'Broca de Diamante','Bosch',8.50,7,['6mm','8mm','10mm','12mm']],
];

echo "-- PRODUCTOS\n";
$prodRows = [];
$invRows  = [];
$precRows = [];
$pidx = 2; // start after cat 1 "General"
$prodId = 1;

foreach ($templates as $t) {
    [$catId, $nombre, $marca, $baseCost, $provId, $variants] = $t;
    foreach ($variants as $variant) {
        $codigo   = 'FER-' . str_pad($prodId, 5, '0', STR_PAD_LEFT);
        $barcode  = '7501' . str_pad($prodId, 9, '0', STR_PAD_LEFT);
        // slight cost variation per variant
        $costVar  = $baseCost * (0.88 + ($prodId % 5) * 0.06);
        $cost     = round($costVar, 4);
        $priceA   = round($cost * 1.35, 2); // 35% margen
        $priceB   = round($cost * 1.25, 2);
        $priceC   = round($cost * 1.15, 2);
        $fullName = addslashes("{$nombre} {$variant}");
        $marcaQ   = addslashes($marca);
        $minStock = ($catId >= 41 && $catId <= 44) ? 50 : 5; // más min para tornillería

        $prodRows[] = "(1,{$catId},{$provId},'{$codigo}','{$fullName}',NULL,'{$marcaQ}','{$barcode}',{$cost},{$minStock},7.00,'activo')";
        $invRows[]  = "(@pid+{$prodId}-1,1,100,{$minStock},500,'{$codigo}',{$cost},{$cost},NOW())";
        $precRows[] = "(@pid+{$prodId}-1,'A',{$priceA},CURDATE())";
        $precRows[] = "(@pid+{$prodId}-1,'B',{$priceB},CURDATE())";
        $precRows[] = "(@pid+{$prodId}-1,'C',{$priceC},CURDATE())";
        $prodId++;
    }
}

// Insert products in batches of 200
$chunks = array_chunk($prodRows, 200);
foreach ($chunks as $chunk) {
    echo "INSERT INTO productos (empresa_id,categoria_id,proveedor_id,codigo,nombre,descripcion,marca,codigo_barras,costo,stock_minimo,itbms,estado) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

// Get first inserted product ID for inventory and prices
echo "SET @pid = (SELECT MIN(producto_id) FROM productos WHERE codigo LIKE 'FER-%');\n\n";

// Inventory in batches of 200
$invChunks = array_chunk($invRows, 200);
echo "-- INVENTARIO\n";
foreach ($invChunks as $chunk) {
    echo "INSERT INTO inventario (producto_id,deposito_id,existencia,minimo,maximo,ubicacion,costo_promedio,ultimo_costo,fecha_actualizacion) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

// Prices in batches of 300
$precChunks = array_chunk($precRows, 300);
echo "-- PRECIOS\n";
foreach ($precChunks as $chunk) {
    echo "INSERT INTO precios_productos (producto_id,tipo_precio,precio,fecha_inicio) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

// ── CLIENTES ──────────────────────────────────────────────────────────────────
echo "-- CLIENTES\n";
$clientes = [
    ['CLI001','Constructora Vargas Hermanos S.A.','empresa','NT-001-001','1','Calle 50 Local 3','info@constructoravargas.com','507-6100-0001',5000.00,30],
    ['CLI002','Ferretería El Tornillo S.A.','empresa','NT-002-002','2','Ave Central 15','compras@eltornillo.com','507-6100-0002',3000.00,15],
    ['CLI003','Ing. Carlos Pérez','persona','8-800-001','0','Panamá','cperez@gmail.com','507-6100-0003',1000.00,0],
    ['CLI004','Constructora Los Altos S.A.','empresa','NT-003-003','3','San Francisco','lsaltos@constructoras.com','507-6100-0004',8000.00,30],
    ['CLI005','Arq. María González','persona','8-801-001','5','Miraflores','mgonzalez@arquitectura.com','507-6100-0005',500.00,0],
    ['CLI006','Taller Mecánico Ríos','empresa','NT-004-004','4','El Chorrillo','tallrios@gmail.com','507-6100-0006',2000.00,15],
    ['CLI007','Hotel Panamá Central','empresa','NT-005-005','5','Casco Antiguo','compras@hotelpanama.com','507-6100-0007',4000.00,30],
    ['CLI008','Juan Morales','persona','8-802-002','0','','jmorales@hotmail.com','507-6100-0008',200.00,0],
    ['CLI009','Multihogar S.A.','empresa','NT-006-006','6','Transistmica','compras@multihogar.com','507-6100-0009',1500.00,15],
    ['CLI010','Constructora Istmo S.A.','empresa','NT-007-007','7','Costa del Este','info@istmo.com','507-6100-0010',10000.00,45],
    ['CLI011','Pedro Ríos','persona','8-803-003','0','','','507-6100-0011',0.00,0],
    ['CLI012','Empresa Eléctrica del Sur S.A.','empresa','NT-008-008','8','San Miguelito','compras@elecsur.com','507-6100-0012',6000.00,30],
    ['CLI013','Ana Castillo','persona','8-804-004','0','La Chorrera','acastillo@gmail.com','507-6100-0013',300.00,0],
    ['CLI014','Plomería y Servicios León S.A.','empresa','NT-009-009','9','Arraiján','info@plomerialeon.com','507-6100-0014',2500.00,15],
    ['CLI015','Roberto Herrera','persona','8-805-005','0','','robertoh@gmail.com','507-6100-0015',0.00,0],
    ['CLI016','Inmobiliaria Costa Verde S.A.','empresa','NT-010-010','0','Betania','compras@costaver.com','507-6100-0016',12000.00,60],
    ['CLI017','Luis Mendoza','persona','8-806-006','0','','','507-6100-0017',0.00,0],
    ['CLI018','Suplidora Industrial Panamá S.A.','empresa','NT-011-011','1','Tocumen','ventas@suplidora.com','507-6100-0018',5000.00,30],
    ['CLI019','Santiago Núñez','persona','8-807-007','0','','snunez@gmail.com','507-6100-0019',100.00,0],
    ['CLI020','Constructora Mar Azul S.A.','empresa','NT-012-012','2','Juan Díaz','info@marazul.com','507-6100-0020',7000.00,30],
    ['CLI021','Diana Acosta','persona','8-808-008','0','','','507-6100-0021',0.00,0],
    ['CLI022','Taller de Soldadura Castillo','empresa','NT-013-013','3','Calidonia','tallcastillo@gmail.com','507-6100-0022',1500.00,15],
    ['CLI023','Francisco Lara','persona','8-809-009','0','','flara@gmail.com','507-6100-0023',200.00,0],
    ['CLI024','Hotel Las Palmas S.A.','empresa','NT-014-014','4','Coronado','compras@laspalmas.com','507-6100-0024',3000.00,30],
    ['CLI025','Cecilia Mora','persona','8-810-010','0','','cmora@gmail.com','507-6100-0025',0.00,0],
    ['CLI026','Constructora Panamá 2050','empresa','NT-015-015','5','Miraflores','info@p2050.com','507-6100-0026',15000.00,45],
    ['CLI027','Eléctrica Servihogar','empresa','NT-016-016','6','Pedregal','ventas@servihogar.com','507-6100-0027',2000.00,15],
    ['CLI028','Mauricio Vega','persona','8-811-011','0','','','507-6100-0028',0.00,0],
    ['CLI029','Clínica del Sur S.A.','empresa','NT-017-017','7','San Miguelito','admin@clinicasur.com','507-6100-0029',1000.00,30],
    ['CLI030','Irene Solano','persona','8-812-012','0','','isolano@gmail.com','507-6100-0030',150.00,0],
    ['CLI031','Mueblería El Roble','empresa','NT-018-018','8','El Cangrejo','compras@elroble.com','507-6100-0031',2000.00,15],
    ['CLI032','Andrés Chávez','persona','8-813-013','0','','','507-6100-0032',0.00,0],
    ['CLI033','Pinturas y Acabados Finesse','empresa','NT-019-019','9','Balboa','ventas@finesse.com','507-6100-0033',3000.00,30],
    ['CLI034','Beatriz Fuentes','persona','8-814-014','0','','bfuentes@gmail.com','507-6100-0034',100.00,0],
    ['CLI035','Almacén La Confianza','empresa','NT-020-020','0','Colón','info@laconfianza.com','507-6100-0035',4000.00,30],
    ['CLI036','Emilio Salas','persona','8-815-015','0','','','507-6100-0036',0.00,0],
    ['CLI037','Seguridad Panamá S.A.','empresa','NT-021-021','1','Parque Lefevre','compras@segpan.com','507-6100-0037',1500.00,15],
    ['CLI038','Viviana Ramos','persona','8-816-016','0','','vramos@gmail.com','507-6100-0038',300.00,0],
    ['CLI039','Centro Médico del Este','empresa','NT-022-022','2','Panama East','admin@centroeste.com','507-6100-0039',2000.00,30],
    ['CLI040','Gustavo Pinto','persona','8-817-017','0','','gpinto@gmail.com','507-6100-0040',0.00,0],
    ['CLI041','Colegio La Salle','empresa','NT-023-023','3','Bella Vista','admin@lasalle.edu.pa','507-6100-0041',1000.00,30],
    ['CLI042','Raúl Espino','persona','8-818-018','0','','','507-6100-0042',0.00,0],
    ['CLI043','Ferretería Nueva Era','empresa','NT-024-024','4','Arraijan','compras@nuevaera.com','507-6100-0043',3000.00,15],
    ['CLI044','Silvia Torres','persona','8-819-019','0','','storres@gmail.com','507-6100-0044',200.00,0],
    ['CLI045','Municipio de Panamá','empresa','NT-025-025','5','Casco Antiguo','compras@municipio.gob.pa','507-6100-0045',20000.00,60],
    ['CLI046','Orlando Méndez','persona','8-820-020','0','','omendez@gmail.com','507-6100-0046',100.00,0],
    ['CLI047','Constructora Bahía Sur','empresa','NT-026-026','6','Juan Díaz','info@bahiasur.com','507-6100-0047',8000.00,30],
    ['CLI048','Alejandra Cruz','persona','8-821-021','0','','','507-6100-0048',0.00,0],
    ['CLI049','Supermercado Los Andes','empresa','NT-027-027','7','San Miguelito','compras@losandes.com','507-6100-0049',2000.00,15],
    ['CLI050','Ernesto Vargas','persona','8-822-022','0','','evargas@gmail.com','507-6100-0050',300.00,0],
];

// Cliente genérico ya existe (id=1), start at 2
$cliRows = [];
foreach ($clientes as $idx => $c) {
    $cid = $idx + 2;
    $nombre = addslashes($c[1]);
    $dir = addslashes($c[5]); // direccion = index 5
    $cliRows[] = "({$cid},1,'{$c[0]}','{$nombre}','{$c[2]}','{$c[3]}','{$c[4]}','{$c[7]}','{$c[6]}','{$dir}',{$c[8]},0,{$c[8]},0,{$c[9]},7.00,'activo')";
}
echo "INSERT INTO clientes (cliente_id,empresa_id,codigo,nombre,tipo,ruc,dv,telefono,email,direccion,limite_credito,saldo,cupo_credito,saldo_pendiente,dias_credito,itbms,estado) VALUES\n";
echo implode(",\n", $cliRows) . ";\n\n";

// ── COMPRAS ───────────────────────────────────────────────────────────────────
echo "-- COMPRAS\n";
// 24 purchase orders (1 per ~2.5 days over 2 months)
$purchases = [];
$purchaseDetails = [];
$purchaseId = 1;

// Dates from 2 months ago
$startDate = mktime(0,0,0,3,19,2026);
$daysSpan  = 61;

for ($i = 0; $i < 24; $i++) {
    $dayOffset  = (int)($i * ($daysSpan / 24));
    $fechaCompra = date('Y-m-d', $startDate + $dayOffset * 86400 + rand(28800, 57600));
    $fechaRec    = date('Y-m-d H:i:s', strtotime($fechaCompra) + 86400 * rand(1,3));
    $provId      = ($i % 8) + 1;
    $numFact     = 'OC-2026-' . str_pad($purchaseId, 4, '0', STR_PAD_LEFT);
    $numFactProv = 'FPROV-' . str_pad($purchaseId, 6, '0', STR_PAD_LEFT);
    $userId      = ($i % 3 === 0) ? 1 : 3; // admin o supervisor

    // 8-15 items per purchase
    $numItems = rand(8, 15);
    $subtotal = 0;
    $itbms    = 0;

    for ($j = 0; $j < $numItems; $j++) {
        $catOffset  = ($i * 3 + $j) % count($templates);
        $baseTemplate = $templates[$catOffset];
        $prodOffset   = array_sum(array_map(fn($t) => count($t[5]), array_slice($templates, 0, $catOffset)));
        $variantIdx   = $j % count($baseTemplate[5]);
        $localProdId  = $prodOffset + $variantIdx + 1;

        $costo     = round($baseTemplate[3] * (0.88 + ($j % 5) * 0.06), 2);
        $cantidad  = rand(5, 50);
        $itbmsPct  = 0.07;
        $lineItbms = round($cantidad * $costo * $itbmsPct, 2);
        $lineTotal = round($cantidad * $costo + $lineItbms, 2);
        $subtotal += round($cantidad * $costo, 2);
        $itbms    += $lineItbms;

        $purchaseDetails[] = "({$purchaseId},@pid+{$localProdId}-1,{$cantidad},{$cantidad},{$costo},{$lineItbms},{$lineTotal})";
    }

    $total = round($subtotal + $itbms, 2);
    $subtotal = round($subtotal, 2);
    $itbms    = round($itbms, 2);

    $purchases[] = "({$purchaseId},1,1,1,{$provId},'{$numFact}','{$numFactProv}',{$subtotal},{$itbms},{$total},'recibida',1,'{$fechaCompra}','{$fechaRec}')";
    $purchaseId++;
}

echo "INSERT INTO compras (compra_id,sucursal_id,empresa_id,deposito_id,proveedor_id,numero_factura,numero_factura_proveedor,monto_subtotal,monto_itbms,monto_total,estado,usuario_id,fecha_compra,fecha_recepcion) VALUES\n";
echo implode(",\n", $purchases) . ";\n\n";

// Compras detalle in batches
$detChunks = array_chunk($purchaseDetails, 100);
echo "INSERT INTO compras_detalle (compra_id,producto_id,cantidad,cantidad_recibida,costo,itbms,total_linea) VALUES\n";
echo implode(",\n", array_merge(...$detChunks)) . ";\n\n";

// ── VENTAS ────────────────────────────────────────────────────────────────────
echo "-- VENTAS\n";
// ~10 sales/day × 61 days = ~610 sales
$ventaRows   = [];
$ventaDetRows = [];
$ventaId     = 1;
$invoiceSeq  = 1;

// Client IDs available
$clientIds = array_merge([1], range(2, 51)); // 1 + 50 clientes

for ($day = 0; $day < 61; $day++) {
    $salesDay = rand(7, 14);
    for ($s = 0; $s < $salesDay; $s++) {
        $fecha   = date('Y-m-d H:i:s', $startDate + $day * 86400 + rand(28800, 72000));
        $numFact = 'F' . date('Ymd', $startDate + $day * 86400) . str_pad($invoiceSeq, 6, '0', STR_PAD_LEFT);
        $invoiceSeq++;

        $clientId   = $clientIds[($ventaId * 7) % count($clientIds)];
        $vendedorId = [1,2,3,4][($ventaId % 4)];
        $formaPago  = ['efectivo','tarjeta','transferencia'][($ventaId % 3)];

        // 2-6 items per sale
        $numItems = rand(2, 6);
        $subtotal = 0; $itbmsTot = 0; $descTot = 0; $costoTot = 0;
        $details  = [];

        for ($it = 0; $it < $numItems; $it++) {
            $tIdx    = ($ventaId * 3 + $it * 7) % count($templates);
            $tpl     = $templates[$tIdx];
            $vIdx    = ($it + $ventaId) % count($tpl[5]);
            $prodOff = 0;
            for ($ti = 0; $ti < $tIdx; $ti++) $prodOff += count($templates[$ti][5]);
            $localPid = $prodOff + $vIdx + 1;

            $costo   = round($tpl[3] * (0.88 + ($it % 5) * 0.06), 4);
            $precio  = round($costo * 1.35, 2);
            $cant    = rand(1, 10);
            $desc    = 0;
            $itbmsL  = round($cant * $precio * 0.07, 2);
            $total_l = round($cant * $precio + $itbmsL - $desc, 2);

            $subtotal  += round($cant * $precio, 2);
            $itbmsTot  += $itbmsL;
            $costoTot  += round($cant * $costo, 2);

            $details[] = "({$ventaId},@pid+{$localPid}-1,1,{$cant},{$precio},{$costo},{$itbmsL},{$desc},{$total_l})";
        }

        $total = round($subtotal + $itbmsTot - $descTot, 2);
        $ventaRows[] = "({$ventaId},1,1,'{$numFact}',{$clientId},{$vendedorId}," . round($subtotal,2) . ",{$itbmsTot},{$descTot},{$total}," . round($costoTot,2) . ",'{$formaPago}','pagada','{$fecha}')";
        foreach ($details as $d) $ventaDetRows[] = $d;
        $ventaId++;
    }
}

// Insert ventas in batches of 100
$ventaChunks = array_chunk($ventaRows, 100);
foreach ($ventaChunks as $chunk) {
    echo "INSERT INTO ventas (venta_id,sucursal_id,empresa_id,numero_factura,cliente_id,vendedor_id,subtotal,itbms,descuento,total,costo,forma_pago,estado,fecha) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

// Venta detalles in batches of 200
$vdChunks = array_chunk($ventaDetRows, 200);
foreach ($vdChunks as $chunk) {
    echo "INSERT INTO ventas_detalle (venta_id,producto_id,deposito_id,cantidad,precio,costo,itbms,descuento,total_linea) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

// ── COTIZACIONES ──────────────────────────────────────────────────────────────
echo "-- COTIZACIONES\n";
$cotRows    = [];
$cotDetRows = [];
$cotId      = 1;
$estados    = ['pendiente','aprobada','rechazada','convertida'];

for ($i = 0; $i < 80; $i++) {
    $dayOff  = rand(0, 60);
    $fecha   = date('Y-m-d H:i:s', $startDate + $dayOff * 86400 + rand(28800, 64800));
    $venc    = date('Y-m-d', $startDate + $dayOff * 86400 + 15 * 86400);
    $numero  = 'COT-2026-' . str_pad($cotId, 4, '0', STR_PAD_LEFT);
    $estado  = $estados[$i % 4];
    $clientId  = $clientIds[($cotId * 11) % count($clientIds)];
    $vendId    = [1,3,4][($cotId % 3)];

    $numItems = rand(3, 8);
    $subtotal = 0; $itbmsTot = 0;
    $dets = [];

    for ($it = 0; $it < $numItems; $it++) {
        $tIdx    = ($cotId * 5 + $it * 3) % count($templates);
        $tpl     = $templates[$tIdx];
        $vIdx    = $it % count($tpl[5]);
        $prodOff = 0;
        for ($ti = 0; $ti < $tIdx; $ti++) $prodOff += count($templates[$ti][5]);
        $localPid = $prodOff + $vIdx + 1;

        $costo  = round($tpl[3] * (0.88 + ($it % 5) * 0.06), 4);
        $precio = round($costo * 1.35, 2);
        $cant   = rand(1, 20);
        $itbmsL = round($cant * $precio * 0.07, 2);
        $total_l = round($cant * $precio + $itbmsL, 2);
        $subtotal += round($cant * $precio, 2);
        $itbmsTot += $itbmsL;

        $dets[] = "({$cotId},@pid+{$localPid}-1,{$cant},{$precio},{$itbmsL},0,{$total_l})";
    }

    $total = round($subtotal + $itbmsTot, 2);
    $cotRows[] = "({$cotId},1,1,'{$numero}',{$clientId},{$vendId}," . round($subtotal,2) . ",{$itbmsTot},0,{$total},'{$estado}','{$venc}','{$fecha}')";
    foreach ($dets as $d) $cotDetRows[] = $d;
    $cotId++;
}

$cotChunks = array_chunk($cotRows, 80);
foreach ($cotChunks as $chunk) {
    echo "INSERT INTO cotizaciones (cotizacion_id,sucursal_id,empresa_id,numero,cliente_id,vendedor_id,subtotal,itbms,descuento,total,estado,fecha_vencimiento,fecha) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

$cdChunks = array_chunk($cotDetRows, 200);
foreach ($cdChunks as $chunk) {
    echo "INSERT INTO cotizaciones_detalle (cotizacion_id,producto_id,cantidad,precio,itbms,descuento,total_linea) VALUES\n";
    echo implode(",\n", $chunk) . ";\n";
}
echo "\n";

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "SELECT 'Demo ferretería insertado OK' AS resultado;\n";
echo "SELECT COUNT(*) AS total_productos FROM productos;\n";
echo "SELECT COUNT(*) AS total_ventas FROM ventas;\n";
echo "SELECT COUNT(*) AS total_clientes FROM clientes;\n";
