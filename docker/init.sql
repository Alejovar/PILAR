
CREATE DATABASE IF NOT EXISTS KitchenLink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE KitchenLink;


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `llya_40123800_KichenLink`
--

-- --------------------------------------------------------

--
-- Table structure for table `bar_production_history`
--

CREATE TABLE `bar_production_history` (
  `id` int(11) NOT NULL,
  `original_detail_id` int(11) NOT NULL COMMENT 'El ID del registro original en `order_details`',
  `order_id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL COMMENT 'El número de la mesa donde se ordenó',
  `batch_timestamp` timestamp NOT NULL COMMENT 'Agrupa los ítems enviados juntos en la misma comanda',
  `service_time` int(11) NOT NULL COMMENT 'El tiempo de servicio (1, 2, 3...) para agrupar en la comanda',
  `server_name` varchar(255) DEFAULT NULL COMMENT 'El nombre del mesero que tomó la orden',
  `product_name` varchar(255) NOT NULL COMMENT 'El nombre del producto en el momento del pedido',
  `modifier_name` varchar(255) DEFAULT NULL COMMENT 'Nombre del modificador seleccionado',
  `quantity` int(11) NOT NULL,
  `special_notes` text DEFAULT NULL,
  `timestamp_added` datetime NOT NULL COMMENT 'Hora en que el cliente pidió el producto',
  `timestamp_completed` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Hora en que se marcó como LISTO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bar_production_history`
--

-- --------------------------------------------------------

--
-- Table structure for table `cash_shifts`
--

CREATE TABLE `cash_shifts` (
  `shift_id` int(11) NOT NULL,
  `user_id_opened` int(11) NOT NULL COMMENT 'ID del cajero/gerente que abrió',
  `start_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Hora de apertura',
  `end_time` timestamp NULL DEFAULT NULL COMMENT 'Hora de cierre',
  `starting_cash` decimal(10,2) NOT NULL COMMENT 'Fondo de caja inicial',
  `ending_cash` decimal(10,2) DEFAULT NULL COMMENT 'Efectivo contado al cerrar',
  `status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_shifts`
--

-- --------------------------------------------------------

--
-- Table structure for table `kitchen_production_history`
--

CREATE TABLE `kitchen_production_history` (
  `id` int(11) NOT NULL,
  `original_detail_id` int(11) NOT NULL COMMENT 'El ID del registro original en `order_details`',
  `order_id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL COMMENT 'El número de la mesa donde se ordenó',
  `batch_timestamp` timestamp NOT NULL COMMENT 'Agrupa los ítems enviados juntos en la misma comanda',
  `service_time` int(11) NOT NULL COMMENT 'El tiempo de servicio (1, 2, 3...) para agrupar en la comanda',
  `server_name` varchar(255) DEFAULT NULL COMMENT 'El nombre del mesero que tomó la orden',
  `product_name` varchar(255) NOT NULL COMMENT 'El nombre del producto en el momento del pedido',
  `modifier_name` varchar(255) DEFAULT NULL COMMENT 'Nombre del modificador seleccionado',
  `quantity` int(11) NOT NULL,
  `special_notes` text DEFAULT NULL,
  `timestamp_added` datetime NOT NULL COMMENT 'Hora en que el cliente pidió el platillo',
  `timestamp_completed` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Hora en que se marcó como LISTO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kitchen_production_history`
--

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL COMMENT 'Dirección IP del atacante',
  `username` varchar(25) DEFAULT NULL COMMENT 'Nombre de usuario intentado (si se conoce)',
  `device_identifier` varchar(64) DEFAULT NULL,
  `attempt_time` datetime NOT NULL COMMENT 'Momento del intento fallido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `preparation_area` enum('COCINA','BARRA') NOT NULL DEFAULT 'COCINA'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`category_id`, `category_name`, `display_order`, `preparation_area`) VALUES
(1, 'Chilaquiles', 10, 'COCINA'),
(2, 'Otros Productos', 20, 'COCINA'),
(3, 'Muuk Fortaleza del día', 30, 'COCINA'),
(4, 'La Musa Mestiza', 40, 'COCINA'),
(5, 'Keto', 50, 'COCINA'),
(6, 'Menú de niños', 60, 'COCINA'),
(7, 'Sorbitos de amor', 70, 'BARRA'),
(8, 'Sorbitos especiales', 80, 'BARRA'),
(9, 'Amanecer Detox', 90, 'BARRA'),
(10, 'Cheers', 100, 'BARRA'),
(11, 'Amanecer frío', 95, 'BARRA'),
(12, 'Postres', 0, 'COCINA');

-- --------------------------------------------------------

--
-- Table structure for table `modifiers`
--

CREATE TABLE `modifiers` (
  `modifier_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `modifier_name` varchar(100) NOT NULL,
  `modifier_price` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Disponible, 0=Agotado',
  `stock_quantity` int(11) DEFAULT NULL COMMENT 'NULL=Infinito, Número=Cuenta regresiva'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `modifiers`
--

INSERT INTO `modifiers` (`modifier_id`, `group_id`, `modifier_name`, `modifier_price`, `is_active`, `stock_quantity`) VALUES
(36, 3, 'Chocolate', '0.00', 1, NULL),
(35, 3, 'Caramelo', '0.00', 1, NULL),
(34, 3, 'Vainilla', '0.00', 1, NULL),
(33, 2, 'Frío', '0.00', 1, NULL),
(32, 2, 'Caliente', '0.00', 1, NULL),
(31, 1, 'Sin guiso', '0.00', 1, NULL),
(30, 1, 'Papa con chorizo', '0.00', 1, NULL),
(29, 1, 'Rajas poblanas', '0.00', 1, NULL),
(28, 1, 'Nopalitos', '0.00', 1, NULL),
(27, 1, 'Queso en salsa', '0.00', 1, NULL),
(26, 1, 'Picadillo', '0.00', 1, NULL),
(25, 1, 'Huevo en salsa', '0.00', 0, NULL),
(24, 1, 'Chicharrón', '0.00', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `modifier_groups`
--

CREATE TABLE `modifier_groups` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `selection_type` varchar(20) DEFAULT 'SINGLE'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `modifier_groups`
--

INSERT INTO `modifier_groups` (`group_id`, `group_name`, `selection_type`) VALUES
(1, 'Guiso a Elegir', 'SINGLE'),
(2, 'Temperatura', 'SINGLE'),
(3, 'Sabor Bebida Fría', 'SINGLE');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `order_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'PENDING',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'El total calculado de la orden'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `detail_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_order` decimal(10,2) NOT NULL,
  `special_notes` text DEFAULT NULL,
  `item_status` enum('PENDIENTE','EN_PREPARACION','LISTO','COMPLETADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  `modifier_id` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lote_id` int(11) DEFAULT NULL,
  `batch_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el ítem fue cancelado (true) o no (false).',
  `cancellation_reason` varchar(255) DEFAULT NULL COMMENT 'Razón por la que el ítem fue marcado como cancelado.',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp cuando el ítem fue marcado como LISTO por Cocina/Barra.',
  `preparation_area` enum('COCINA','BARRA') NOT NULL COMMENT 'Área de preparación determinada por la categoría del producto.',
  `service_time` int(11) NOT NULL DEFAULT 1 COMMENT 'Secuencia de servicio (tiempo) para agrupar ítems en la comanda. Por defecto es 1.',
  `notified_waiter` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'FALSE=No notificado, TRUE=Ya notificado al mesero'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `order_details`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `modifier_group_id` int(11) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT NULL COMMENT 'NULL = Infinito, Número = Cantidad disponible'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `price`, `category_id`, `is_available`, `modifier_group_id`, `stock_quantity`) VALUES
(115, 'Mi amor bonito', '228.00', 1, 1, NULL, NULL),
(116, 'Nativos', '219.00', 1, 1, NULL, NULL),
(117, 'Doña Susana', '216.00', 1, 0, NULL, NULL),
(118, 'Nene', '216.00', 1, 1, NULL, NULL),
(119, 'La Chef', '219.00', 1, 1, NULL, 1),
(120, 'Mosqueteras', '228.00', 1, 1, NULL, NULL),
(121, 'Levanta muertos', '223.00', 1, 0, NULL, NULL),
(122, 'Pa Que Amarre', '238.00', 1, 1, NULL, NULL),
(123, 'Ingrediente extra', '44.00', 2, 1, NULL, NULL),
(124, 'Tortillas de harina', '0.00', 2, 1, NULL, NULL),
(125, 'Tortillas de maíz', '0.00', 2, 1, NULL, NULL),
(126, 'Tortillas de Nixtamal azul', '44.00', 2, 1, NULL, NULL),
(127, 'La reliquia', '540.00', 2, 1, NULL, NULL),
(128, 'Sureños', '223.00', 3, 1, NULL, NULL),
(129, 'Montados', '228.00', 3, 1, NULL, NULL),
(130, 'Montados nativos', '228.00', 3, 1, NULL, NULL),
(131, 'Montados levantamuertos', '228.00', 3, 1, NULL, NULL),
(132, 'Madrileño', '219.00', 3, 1, NULL, NULL),
(133, 'A la mexicana', '216.00', 3, 0, NULL, NULL),
(134, 'Rebeldía', '228.00', 3, 1, NULL, NULL),
(135, 'México lindo', '116.00', 4, 1, 1, NULL),
(136, 'La regia', '116.00', 4, 1, 1, 5),
(137, 'MUCHOQUESO', '216.00', 5, 1, NULL, NULL),
(138, 'Primera Dama', '239.00', 5, 1, NULL, NULL),
(139, 'La Notario', '228.00', 5, 1, NULL, NULL),
(140, 'El desayuno del Sr. Viktor', '239.00', 5, 1, NULL, NULL),
(141, 'Toast Mestizo', '226.00', 5, 1, NULL, NULL),
(142, 'Retoño de amor', '216.00', 5, 1, NULL, NULL),
(143, 'Sarape de Amor', '0.00', 5, 1, NULL, NULL),
(144, 'Mi saltillo', '216.00', 5, 1, NULL, NULL),
(145, 'Nopales', '216.00', 5, 1, NULL, NULL),
(146, 'Mi abuelita', '128.00', 6, 1, NULL, NULL),
(147, 'Surenito', '118.00', 6, 1, NULL, NULL),
(148, 'Molletin', '109.00', 6, 1, NULL, NULL),
(149, 'Tollimare', '118.00', 7, 1, NULL, NULL),
(150, 'El conta', '119.00', 7, 1, NULL, NULL),
(151, 'Capuccino cabrito', '124.00', 7, 1, NULL, NULL),
(152, 'Olla Mestiza', '74.00', 7, 1, NULL, NULL),
(153, 'Tierra Mestiza', '77.00', 7, 1, NULL, NULL),
(154, 'Americano Mosere', '71.00', 7, 1, NULL, NULL),
(155, 'Americano', '87.00', 7, 1, NULL, 5),
(156, 'Chocomilk', '124.00', 7, 1, 2, NULL),
(157, 'Chocoavena', '124.00', 8, 1, NULL, NULL),
(158, 'Jamaica Guayaba', '112.00', 8, 1, NULL, NULL),
(159, 'Limonada Pink', '119.00', 8, 1, NULL, NULL),
(160, 'Limonada', '117.00', 8, 1, NULL, NULL),
(161, 'Limonada Mineral', '124.00', 8, 1, NULL, NULL),
(162, 'Naranjada', '117.00', 8, 1, NULL, NULL),
(163, 'Naranjada Mineral', '124.00', 8, 1, NULL, NULL),
(164, 'Agua embotellada', '39.00', 8, 1, NULL, NULL),
(165, 'Refresco', '64.00', 8, 1, NULL, NULL),
(166, 'Verde despertar', '124.00', 9, 1, NULL, NULL),
(167, 'Naranja', '87.00', 9, 1, NULL, NULL),
(168, 'Iced Latte', '134.00', 11, 1, 3, NULL),
(169, 'Iced Capuccino', '124.00', 11, 1, NULL, NULL),
(170, 'Café frío', '124.00', 11, 1, NULL, NULL),
(171, 'Mimosas', '184.00', 10, 1, NULL, NULL),
(172, 'Mixología', '224.00', 10, 1, NULL, NULL),
(173, 'Tisanas mestizas', '124.00', 10, 1, NULL, NULL),
(174, 'Carrito Mimosas', '920.00', 10, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `hostess_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `number_of_people` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

-- --------------------------------------------------------

--
-- Table structure for table `reservations_history`
--

CREATE TABLE `reservations_history` (
  `id` int(11) NOT NULL,
  `original_reservation_id` int(11) NOT NULL,
  `hostess_id` int(11) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `number_of_people` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `final_status` enum('completada','cancelada','no-show') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations_history`
--

-- --------------------------------------------------------

--
-- Table structure for table `reservation_tables`
--

CREATE TABLE `reservation_tables` (
  `reservation_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_tables`
--

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_tables`
--

CREATE TABLE `restaurant_tables` (
  `table_id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `assigned_server_id` int(11) NOT NULL,
  `client_count` int(11) NOT NULL,
  `occupied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pre_bill_status` enum('ACTIVE','REQUESTED') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Estado de la ocupación: ACTIVE=en curso, REQUESTED=cuenta solicitada.',
  `locked_by` int(11) DEFAULT NULL COMMENT 'ID del usuario que está editando actualmente la mesa',
  `locked_at` timestamp NULL DEFAULT NULL COMMENT 'Momento exacto en que inició la edición'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `restaurant_tables`
--

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `rol_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `rol_name`) VALUES
(6, 'cajero'),
(5, 'encargado de barra'),
(1, 'gerente'),
(4, 'hostess'),
(3, 'jefe de cocina'),
(2, 'mesero');

-- --------------------------------------------------------

--
-- Table structure for table `sales_history`
--

CREATE TABLE `sales_history` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Número de movimiento único para el ticket',
  `original_order_id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `client_count` int(11) NOT NULL COMMENT 'Número de personas en la mesa',
  `server_name` varchar(255) NOT NULL,
  `cashier_id` int(11) DEFAULT NULL COMMENT 'ID del usuario (cajero) que procesó el pago',
  `time_occupied` timestamp NOT NULL COMMENT 'Hora en que se ocupó la mesa',
  `payment_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Hora en que se pagó la cuenta',
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tip_amount_card` decimal(10,2) DEFAULT 0.00 COMMENT 'Propina pagada con tarjeta',
  `grand_total` decimal(10,2) NOT NULL COMMENT 'Monto final pagado por el cliente',
  `is_courtesy` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'TRUE si toda la venta fue una cortesía, FALSE si no.',
  `payment_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Un JSON que describe los métodos de pago, ej: [{"method": "Cash", "amount": 500}]',
  PRIMARY KEY (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_history`
--

-- --------------------------------------------------------

--
-- Table structure for table `sales_history_details`
--

CREATE TABLE `sales_history_details` (
  `sale_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `modifier_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_order` decimal(10,2) NOT NULL,
  `was_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sale_detail_id`),
  KEY `idx_sale_id` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_history_details`
--

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `status` enum('disponible','ocupado') NOT NULL DEFAULT 'disponible',
  `status_changed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`table_name`) VALUES
('Mesa 10'), ('Mesa 11'), ('Mesa 12'), ('Mesa 13'),
('Mesa 20'), ('Mesa 21'), ('Mesa 22'), ('Mesa 23'),
('Mesa 30'), ('Mesa 40'), ('Mesa 41'), ('Mesa 42'),
('Mesa 50'), ('Mesa 51'), ('Mesa 52'),
('Mesa 60'), ('Mesa 61'), ('Mesa 62'),
('Mesa 70'), ('Mesa 71'), ('Mesa 72'),
('Mesa 80'), ('Mesa 81');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(25) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user`, `password`, `name`, `rol_id`, `session_token`, `status`) VALUES
(2, 'dican', '$2y$10$2BQEB5/zNWpsWASS1BVFpO7yKODtgWUtg6O1cRijLF0F87YMsxEKW', 'Diego Can', 1, '1a749702d364de243656fa622abdb1eaac910ab9c67abec0e0965839fb04db18', 'ACTIVO'),
(3, 'leovan', '$2y$10$R1wd50YcY2XrXWB6QIZa8.49/XA4Tk7RsnqH6arBRbqCIREYTaDIy', 'Leodan Carrizales Rodriguez', 2, '4e096a5ffe352725fae03f6079e4a10111506db4be9e724a11a4e8591d3008c7', 'ACTIVO'),
(4, 'Marmon', '$2y$10$L.t1ATALfXFqEPIqXPqB5uJ.M5/ryW4l1nWSBS1aKQyMgMu35eIIG', 'Mareli Moncada', 4, NULL, 'ACTIVO'),
(5, 'saira', '$2y$10$qzxVQObXw6rgISg3jplQOeDcWxiHkMilK8qVNI/dB4CBYTE3KuDnO', 'Saira Julizza Olivas', 2, NULL, 'ACTIVO'),
(6, 'nayesan', '$2y$10$57z1VpzMkOnzzWTJfC0mYep7Tze10rnewr6/cNBEd62z3TE39uIi2', 'Nayeli Santoy', 3, NULL, 'ACTIVO'),
(7, 'marcomaco', '$2y$10$04ZuDYSuK9L57dk3mHUwnOvBtQ.azgX/l0DFM4eVmN8m1qB44QzhG', 'Marco Castro ', 5, NULL, 'ACTIVO'),
(8, 'yamiyami', '$2y$10$V7AhpwqzZLZ3XfmBTo7GKO1Ccj3T0fyomEjjV9iXSFELw3L7zBdaS', 'Yamileth Sanchez', 2, '7db44e5d0d6673adea724bdf982697b0dbd4d6b2f4a7954f687644e10c6bf15e', 'ACTIVO'),
(9, 'platano', '$2y$10$eGycNSvT8OJDncgDJHgIfesxzrMC9GzfzPofL6NjYwYHAXkW4hkba', 'Daniel Quiroz', 2, NULL, 'ACTIVO'),
(10, 'brianita', '$2y$10$YabNzsIjDTLi5rjgpl11IOVtC5KZ7WuHdzrvzoOlzt0.GHd0s8IEW', 'Briana Canciller', 6, NULL, 'ACTIVO'),
(11, 'ivannal', '$2y$10$gJYS.NacVGvr5Vt5rPNSC.NERGZLiMRct6N9SM1dGOww6lnj4GtHO', 'Ivan Salas', 2, NULL, 'INACTIVO'),
(12, 'alvarito', '$2y$10$pY4j53FK3Kl2HNjSknH2I.5Z.bA471BwN7f8WBOPlGwtgYeO/EY7S', 'Alvaro Diaz', 2, NULL, 'ACTIVO'),
(13, 'alejovar', '$2y$10$AaxQ8Nj4Jj/ReOmViDDgmOEYYM1TLbrbhe24tYTVVHcJ/.wRc3n66', 'Alejandro Vargas', 2, NULL, 'ACTIVO'),
(14, 'miranda', '$2y$10$xFdYQGnzDaIwng7qDXRkOe5u4esJTu1uA3Zdg4JHOxPeksFSAE9KO', 'Miranda Neyra', 2, NULL, 'ACTIVO'),
(15, 'tobillos', '$2y$10$iv6BFreBkdOiEtvkrEsljOgDX/5eK6mN7c2YgVJe6Azbafg34Xud2', 'Leonardo Toviaz', 2, 'fa45c4d11ac2575e0e29fbbbfa53cbceb1b1b8422d4b2a02cdd7fe3b43ee556d', 'ACTIVO'),
(16, 'saenponystan', '$2y$10$Q0.TSeYNs6BsLqzSTIVOb.Tj5pP1TSWtI5UGfFqyPjrQOnfEpA.ji', 'Luis Antonio Saenz Jimenez', 4, NULL, 'ACTIVO');

-- --------------------------------------------------------

--
-- Table structure for table `waiting_list`
--

CREATE TABLE `waiting_list` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `number_of_people` int(11) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `waiting_list`
--

-- --------------------------------------------------------

--
-- Table structure for table `waiting_list_history`
--

CREATE TABLE `waiting_list_history` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `number_of_people` int(11) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `archived_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('seated','cancelled') NOT NULL COMMENT 'Indica si el cliente fue sentado o canceló.',
  `tables_assigned` varchar(255) DEFAULT NULL COMMENT 'Nombres de las mesas asignadas, separados por comas'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `waiting_list_history`
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bar_production_history`
--
ALTER TABLE `bar_production_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_completed_date` (`timestamp_completed`);

--
-- Indexes for table `cash_shifts`
--
ALTER TABLE `cash_shifts`
  ADD PRIMARY KEY (`shift_id`),
  ADD KEY `fk_shift_user` (`user_id_opened`);

--
-- Indexes for table `kitchen_production_history`
--
ALTER TABLE `kitchen_production_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_completed_date` (`timestamp_completed`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `UK_CategoryName` (`category_name`);

--
-- Indexes for table `modifiers`
--
ALTER TABLE `modifiers`
  ADD PRIMARY KEY (`modifier_id`),
  ADD KEY `FK_OptionGroup` (`group_id`);

--
-- Indexes for table `modifier_groups`
--
ALTER TABLE `modifier_groups`
  ADD PRIMARY KEY (`group_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `FK_OrderTable` (`table_id`),
  ADD KEY `FK_OrderServer` (`server_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `FK_DetailOrder` (`order_id`),
  ADD KEY `FK_DetailProduct` (`product_id`),
  ADD KEY `FK_DetailModifier` (`modifier_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `FK_ProductCategory` (`category_id`),
  ADD KEY `FK_ProductModifierGroup` (`modifier_group_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reservations_users` (`hostess_id`),
  ADD KEY `idx_reservation_date` (`reservation_date`);

--
-- Indexes for table `reservations_history`
--
ALTER TABLE `reservations_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservation_tables`
--
ALTER TABLE `reservation_tables`
  ADD PRIMARY KEY (`reservation_id`,`table_id`),
  ADD KEY `fk_rt_tables` (`table_id`);

--
-- Indexes for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`table_id`),
  ADD UNIQUE KEY `UK_TableNumber` (`table_number`),
  ADD KEY `FK_ServerAssignment` (`assigned_server_id`),
  ADD KEY `fk_table_lock_user` (`locked_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rol_name` (`rol_name`);

--
-- Indexes for table `sales_history_details`
--
ALTER TABLE `sales_history_details`
  ADD PRIMARY KEY (`sale_detail_id`),
  ADD KEY `fk_sale_history` (`sale_id`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user` (`user`),
  ADD KEY `fk_users_roles` (`rol_id`);

--
-- Indexes for table `waiting_list`
--
ALTER TABLE `waiting_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `waiting_list_history`
--
ALTER TABLE `waiting_list_history`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bar_production_history`
--
ALTER TABLE `bar_production_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `cash_shifts`
--
ALTER TABLE `cash_shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `kitchen_production_history`
--
ALTER TABLE `kitchen_production_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `modifiers`
--
ALTER TABLE `modifiers`
  MODIFY `modifier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `modifier_groups`
--
ALTER TABLE `modifier_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=791;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reservations_history`
--
ALTER TABLE `reservations_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  MODIFY `table_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales_history`
--
ALTER TABLE `sales_history`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Número de movimiento único para el ticket';

--
-- AUTO_INCREMENT for table `sales_history_details`
--
ALTER TABLE `sales_history_details`
  MODIFY `sale_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4079;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `waiting_list`
--
ALTER TABLE `waiting_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `waiting_list_history`
--
ALTER TABLE `waiting_list_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cash_shifts`
--
ALTER TABLE `cash_shifts`
  ADD CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id_opened`) REFERENCES `users` (`id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_users` FOREIGN KEY (`hostess_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reservation_tables`
--
ALTER TABLE `reservation_tables`
  ADD CONSTRAINT `fk_rt_reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rt_tables` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_history_details`
--
ALTER TABLE `sales_history_details`
  ADD CONSTRAINT `fk_sale_history` FOREIGN KEY (`sale_id`) REFERENCES `sales_history` (`sale_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_roles` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;





