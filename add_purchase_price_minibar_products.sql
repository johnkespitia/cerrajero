-- Ejecutar en tu base de datos MySQL/MariaDB si las migraciones no se han corrido.
-- 1) Añadir columna purchase_price a minibar_products
-- 2) Crear tabla minibar_warehouse_stock (inventario de bodega)

-- 1. Columna precio de costo en productos
ALTER TABLE `minibar_products`
ADD COLUMN `purchase_price` DECIMAL(10, 2) NULL COMMENT 'Precio de costo/compra' AFTER `sale_price`;

-- 2. Tabla inventario de bodega (solo si no existe)
CREATE TABLE IF NOT EXISTS `minibar_warehouse_stock` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `current_quantity` int NOT NULL DEFAULT '0' COMMENT 'Cantidad disponible en bodega',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `minibar_warehouse_stock_product_id_unique` (`product_id`),
  CONSTRAINT `minibar_warehouse_stock_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `minibar_products` (`id`) ON DELETE CASCADE
);
