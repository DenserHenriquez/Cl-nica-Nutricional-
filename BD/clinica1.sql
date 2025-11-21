-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-11-2025 a las 03:00:22
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `clinica1`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alimentos_nutricionales`
--

CREATE TABLE `alimentos_nutricionales` (
  `id_alimento` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('alimento','plato') NOT NULL,
  `calorias` decimal(10,2) NOT NULL,
  `proteinas` decimal(10,2) NOT NULL,
  `grasas` decimal(10,2) NOT NULL,
  `carbohidratos` decimal(10,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alimentos_nutricionales`
--

INSERT INTO `alimentos_nutricionales` (`id_alimento`, `nombre`, `tipo`, `calorias`, `proteinas`, `grasas`, `carbohidratos`, `created_by`, `fecha_creacion`) VALUES
(1, 'Huevo Cocido', 'alimento', 78.00, 6.30, 5.30, 0.60, 1, '2025-11-07 18:37:13'),
(2, 'Pollo a la plancha con Arroz', 'plato', 420.00, 38.00, 12.00, 40.00, 1, '2025-11-07 18:45:03'),
(3, 'papas', 'alimento', 0.03, 0.08, 0.20, 20.00, 9, '2025-11-18 23:10:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alimentos_registro`
--

CREATE TABLE `alimentos_registro` (
  `id` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo_comida` varchar(20) NOT NULL,
  `descripcion` text NOT NULL,
  `hora` time NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alimentos_registro`
--

INSERT INTO `alimentos_registro` (`id`, `id_pacientes`, `fecha`, `tipo_comida`, `descripcion`, `hora`, `foto_path`, `created_at`) VALUES
(1, 6, '2025-10-26', 'desayuno', 'Pan integral con huevo picado , jamon y ensalada', '07:00:00', 'assets/images/alimentos/paciente_6_20251028_025321_b05ab5cf.jpg', '2025-10-27 19:53:21'),
(2, 3, '2025-10-17', 'desayuno', 'Pan Integral con aguacate y huevo tibio', '08:10:00', 'assets/images/alimentos/paciente_3_20251028_031206_38c2c6c7.jpg', '2025-10-27 20:12:06'),
(3, 3, '2025-10-17', 'almuerzo', 'Pollo a la plancha con ensalada', '12:00:00', 'assets/images/alimentos/paciente_3_20251028_031902_651d8125.png', '2025-10-27 20:19:02'),
(5, 6, '2025-10-12', 'almuerzo', 'Pollo cosido', '12:00:00', 'assets/images/alimentos/paciente_6_20251028_041537_de8301ae.png', '2025-10-27 21:15:37'),
(7, 4, '2025-10-27', 'desayuno', 'Bowl de frutas', '07:30:00', 'assets/images/alimentos/paciente_420251028_064942fd3904f3.png', '2025-10-27 23:49:42'),
(8, 4, '2025-10-28', 'almuerzo', 'Arroz, Pollo y ensalada de aguacate y tomate', '12:00:00', 'assets/images/alimentos/paciente_420251028_065200d392ddce.jpg', '2025-10-27 23:52:00'),
(9, 1, '2025-10-29', 'almuerzo', 'Arroz, pollo, ensalada de aguacate y tomate', '12:20:00', 'assets/images/alimentos/paciente_120251029_224346b63ec590.jpg', '2025-10-29 15:43:46'),
(10, 1, '2025-11-11', 'cena', 'Pan integral con aguacate', '20:23:00', 'assets/images/alimentos/paciente_120251111_022503d1dc2d66.jpg', '2025-11-10 19:25:03'),
(11, 5, '2025-11-11', 'snack', 'pollo', '22:36:00', 'assets/images/alimentos/paciente_520251111_043540365af368.jpg', '2025-11-10 21:35:40'),
(12, 5, '2025-11-21', 'desayuno', 'a', '00:38:00', NULL, '2025-11-10 21:35:53'),
(13, 10, '2025-11-19', 'cena', 'arroz', '20:19:00', 'assets/images/alimentos/paciente_1020251119_011738026f44c6.webp', '2025-11-18 18:17:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id` int(11) NOT NULL,
  `medico_id` int(11) NOT NULL DEFAULT 1,
  `nombre_completo` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `motivo` text DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cancelada','completada') NOT NULL DEFAULT 'pendiente',
  `paciente_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `citas`
--

INSERT INTO `citas` (`id`, `medico_id`, `nombre_completo`, `fecha`, `hora`, `motivo`, `estado`, `paciente_id`, `creado_en`) VALUES
(1, 1, 'anthony', '2025-11-19', '12:00:00', 'si lo quiero', 'confirmada', NULL, '2025-11-19 01:24:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidades`
--

CREATE TABLE `disponibilidades` (
  `id` int(11) NOT NULL,
  `medico_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('libre','bloqueado') NOT NULL DEFAULT 'libre'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `disponibilidades`
--

INSERT INTO `disponibilidades` (`id`, `medico_id`, `fecha`, `hora`, `estado`) VALUES
(1, 1, '2025-11-19', '12:00:00', 'bloqueado'),
(2, 1, '2025-11-19', '12:30:00', 'libre'),
(3, 1, '2025-11-19', '13:00:00', 'libre'),
(4, 1, '2025-11-19', '13:30:00', 'libre'),
(5, 1, '2025-11-19', '14:00:00', 'libre'),
(6, 1, '2025-11-19', '14:30:00', 'libre');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ejercicios`
--

CREATE TABLE `ejercicios` (
  `id_ejercicio` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `tipo_ejercicio` varchar(100) NOT NULL,
  `tiempo` int(11) NOT NULL COMMENT 'Duración en minutos',
  `hora` time NOT NULL DEFAULT '00:00:00',
  `fecha` date NOT NULL,
  `imagen_evidencia` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ejercicios`
--

INSERT INTO `ejercicios` (`id_ejercicio`, `paciente_id`, `tipo_ejercicio`, `tiempo`, `hora`, `fecha`, `imagen_evidencia`, `notas`, `fecha_registro`) VALUES
(1, 1, 'Gimnasio', 30, '00:00:00', '2025-10-27', NULL, 'Realize remo con mancuernas', '2025-10-27 19:19:53'),
(2, 1, 'Caminata', 30, '00:00:00', '2025-10-28', 'uploads/ejercicios/69003be939acb_Caminata.jpg', '', '2025-10-28 03:43:37'),
(3, 1, 'Caminata', 30, '00:00:00', '2025-10-28', 'uploads/ejercicios/69003c817865a_Caminata.jpg', '', '2025-10-28 03:46:09'),
(4, 4, 'Correr', 30, '00:00:00', '2025-10-27', 'uploads/ejercicios/690055ed4d1a3_Caminata.jpg', '', '2025-10-28 05:34:37'),
(5, 5, 'Ciclismo', 180, '23:35:00', '2025-11-12', 'uploads/ejercicios/paciente_5_20251111_043433_47fffd.jpg', 'no', '2025-11-11 03:34:33'),
(6, 10, 'Natación', 40, '19:57:00', '2025-11-18', 'uploads/ejercicios/paciente_10_20251119_015701_17c916.jpg', 'NINGUNA', '2025-11-19 00:57:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expediente`
--

CREATE TABLE `expediente` (
  `id_expediente` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `talla` decimal(5,2) DEFAULT NULL COMMENT 'Talla en cm',
  `peso` decimal(5,2) DEFAULT NULL COMMENT 'Peso en kg',
  `estatura` decimal(5,2) DEFAULT NULL COMMENT 'Estatura en cm',
  `IMC` decimal(5,2) DEFAULT NULL COMMENT 'Índice de Masa Corporal',
  `masa_muscular` decimal(5,2) DEFAULT NULL COMMENT 'Masa muscular en kg',
  `enfermedades_base` text DEFAULT NULL COMMENT 'Enfermedades de base',
  `medicamentos` text DEFAULT NULL COMMENT 'Medicamentos',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `expediente`
--

INSERT INTO `expediente` (`id_expediente`, `id_pacientes`, `talla`, `peso`, `estatura`, `IMC`, `masa_muscular`, `enfermedades_base`, `medicamentos`, `fecha_registro`) VALUES
(1, 5, 172.10, 76.00, 172.01, 25.69, 55.01, 'a', 'b', '2025-10-28 23:40:36'),
(2, 6, 170.00, 70.00, 170.00, 24.22, 55.00, 'ninguno', 'cocacola', '2025-10-31 03:00:52'),
(4, 8, 170.00, 77.00, 170.00, 26.64, 60.00, NULL, NULL, '2025-10-31 03:34:06'),
(5, 5, 172.10, 74.00, 172.01, 25.01, 55.01, 'a', 'b', '2025-11-08 05:32:26'),
(6, 5, 172.10, 72.00, 172.01, 24.33, 55.01, 'a', 'b', '2025-11-08 05:33:18'),
(7, 5, 172.10, 76.00, 172.01, 25.69, 55.01, 'a', 'b', '2025-11-08 05:33:35'),
(8, 5, 172.10, 72.00, 172.01, 24.33, 55.01, 'a', 'b', '2025-11-08 05:34:13'),
(9, 9, 180.00, NULL, 180.00, NULL, 25.00, 'n', 'n', '2025-11-09 04:26:04'),
(10, 5, 180.00, NULL, 180.00, NULL, 25.00, 'a', 'b', '2025-11-11 03:31:31'),
(11, 5, 172.12, 76.00, 172.12, 25.65, 55.01, 'a', 'b', '2025-11-11 03:32:57'),
(12, 6, 180.00, NULL, 180.00, NULL, 25.00, 'a', 'aa', '2025-11-11 03:53:48'),
(13, 7, 170.00, 77.00, 170.00, 26.64, 50.00, 'x', 'x', '2025-11-11 04:05:47'),
(14, 8, 170.00, 77.00, 170.00, 26.64, 50.00, 'no', 'tampoco', '2025-11-11 04:52:45'),
(15, 9, 170.00, 77.00, 170.00, 26.64, 50.00, 'a', 'a', '2025-11-15 03:13:12'),
(16, 10, 170.00, 77.00, 170.00, 26.64, 50.00, NULL, NULL, '2025-11-15 03:17:01'),
(17, 11, 180.00, NULL, 180.00, NULL, 25.00, 'n', 'n', '2025-11-19 00:13:51'),
(18, 12, 180.00, NULL, 180.00, NULL, 25.00, NULL, NULL, '2025-11-19 02:37:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_actualizaciones`
--

CREATE TABLE `historial_actualizaciones` (
  `id_historial` int(11) NOT NULL,
  `id_usuarios` int(11) NOT NULL,
  `campo` varchar(50) NOT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `actualizado_por` int(11) NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_actualizaciones`
--

INSERT INTO `historial_actualizaciones` (`id_historial`, `id_usuarios`, `campo`, `valor_anterior`, `valor_nuevo`, `actualizado_por`, `fecha_actualizacion`) VALUES
(1, 1, 'Contrasena', '$2y$10$DvxzpozBk0pUCMIq9b.qmO1ja18O3ESM9XZWoIooTWZbpa7dyhffi', '$2y$10$g6t.GIf5IXojr/GyBdeKxOwwAkHOezyZYsfpQsPDE1fC0dIWzsoJW', 1, '2025-10-27 18:40:55'),
(2, 1, 'nombre_completo', 'Damaris Bonilla G', 'Damaris Bonilla Garcia', 1, '2025-10-27 18:40:55'),
(3, 1, 'Contrasena', '$2y$10$g6t.GIf5IXojr/GyBdeKxOwwAkHOezyZYsfpQsPDE1fC0dIWzsoJW', '$2y$10$kvbju/mryCg7vBMYlXfDxuuXMnrgU4rczvVTuujQusKXk9Llibj72', 1, '2025-10-27 18:43:48'),
(4, 1, 'telefono', '99553364', '99553210', 1, '2025-10-27 18:43:48'),
(5, 1, 'Contrasena', '$2y$10$kvbju/mryCg7vBMYlXfDxuuXMnrgU4rczvVTuujQusKXk9Llibj72', '$2y$10$ol852jxpvXxZpoiJX2eQQ.bCQHeM4o2gEQKLSRQpoRHJZFrKBb0oO', 1, '2025-10-27 18:46:51'),
(6, 1, 'nombre_completo', 'Damaris Bonilla Garcia', 'Damaris E Bonilla Garcia', 1, '2025-10-27 18:46:51'),
(7, 1, 'telefono', '99553210', '99553211', 1, '2025-10-27 18:46:51'),
(8, 1, 'Contrasena', '$2y$10$ol852jxpvXxZpoiJX2eQQ.bCQHeM4o2gEQKLSRQpoRHJZFrKBb0oO', '$2y$10$eA.D3HQlEoX0bfcVDOcHReCJ/2Om3YtPnFm/a3kKrYpbJcnZ.aue6', 1, '2025-10-27 23:34:25'),
(9, 1, 'nombre_completo', 'Damaris E Bonilla Garcia', 'Damaris  Bonilla Garcia', 1, '2025-10-27 23:34:25'),
(10, 1, 'fecha_nacimiento', '2006-08-01', '2005-08-01', 1, '2025-10-27 23:34:25'),
(11, 1, 'edad', '19', '20', 1, '2025-10-27 23:34:25'),
(12, 1, 'Contrasena', '$2y$10$eA.D3HQlEoX0bfcVDOcHReCJ/2Om3YtPnFm/a3kKrYpbJcnZ.aue6', '$2y$10$N3MAup0mWpMx9ZePPZru1OYzMaHJJIKJtDvwmQ76o56dKUBU6O89e', 1, '2025-10-28 16:01:16'),
(13, 1, 'Contrasena', '$2y$10$N3MAup0mWpMx9ZePPZru1OYzMaHJJIKJtDvwmQ76o56dKUBU6O89e', '$2y$10$SPA6cFDoZ7RC/DgE5PJOheZ3rG7h/taCA9uRbG6e7NAU0IDHCwtdW', 1, '2025-10-30 22:29:27'),
(14, 1, 'telefono', '99553211', '33456012', 1, '2025-10-30 22:29:27'),
(15, 9, 'talla', NULL, '172.12', 9, '2025-11-11 03:32:57'),
(16, 9, 'peso', NULL, '76.00', 9, '2025-11-11 03:32:57'),
(17, 9, 'estatura', NULL, '172.12', 9, '2025-11-11 03:32:57'),
(18, 9, 'IMC', NULL, '25.65', 9, '2025-11-11 03:32:57'),
(19, 9, 'masa_muscular', NULL, '55.01', 9, '2025-11-11 03:32:57'),
(20, 9, 'enfermedades_base', NULL, 'a', 9, '2025-11-11 03:32:57'),
(21, 9, 'medicamentos', NULL, 'b', 9, '2025-11-11 03:32:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id_pacientes` int(11) NOT NULL,
  `id_usuarios` int(11) NOT NULL,
  `nombre_completo` varchar(100) DEFAULT NULL,
  `DNI` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pacientes`
--

INSERT INTO `pacientes` (`id_pacientes`, `id_usuarios`, `nombre_completo`, `DNI`, `fecha_nacimiento`, `edad`, `telefono`, `estado`) VALUES
(1, 1, 'Damaris  Bonilla Garcia', '0823200610125', '2005-08-01', 20, '33456012', 'Activo'),
(2, 2, 'Jose Levi Canales', '1705201000216', '2010-10-08', 15, '32653641', 'Activo'),
(3, 3, 'Juan David Perez ', '0801200015300', '2025-04-08', 25, '33452018', 'Inactivo'),
(4, 4, 'Evelenth Garcia', '0823200013256', '2000-07-06', 25, '33654892', 'Inactivo'),
(5, 9, 'anthony', '0801200012346', '2012-11-05', 13, '99887766', 'Activo'),
(6, 10, 'kaleth', '0801200012346', '2012-11-05', 13, '99887766', 'Activo'),
(7, 12, 'a', '0801200012348', '2025-06-18', 0, '99999911', 'Activo'),
(8, 13, 'leonel messi', '0801200012349', '2025-06-18', 0, '99999912', 'Activo'),
(9, 18, 'paciente12', '1234567891234', '2001-05-17', 24, '99999914', 'Activo'),
(10, 17, 'paciente11', '0823123451234', '2001-05-17', 24, '99999914', 'Activo'),
(11, 19, 'jorge hernandez', '0801200012349', '2012-11-05', 13, '99887768', 'Activo'),
(12, 20, 'mario hernandez', '0801200000001', '2000-11-05', 25, '99888888', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas`
--

CREATE TABLE `recetas` (
  `id` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `ingredientes` text NOT NULL,
  `porciones` int(11) DEFAULT NULL,
  `instrucciones` text DEFAULT NULL,
  `nota_nutricional` text DEFAULT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `id_medico` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `recetas`
--

INSERT INTO `recetas` (`id`, `id_pacientes`, `nombre`, `ingredientes`, `porciones`, `instrucciones`, `nota_nutricional`, `foto_path`, `created_at`, `id_medico`) VALUES
(1, 5, 'Salmón al Horno con Aderezo de Eneldo y Ensalada de Berries', 'Filetes de salmón (4 unidades de 150g), aceite de oliva virgen extra, brócoli (200g), espinacas frescas (150g), arándanos (100g), fresas (150g), nueces (50g), limón (1 unidad), eneldo fresco, sal marina y pimienta.', 4, '1. Preparar Salmón: Precalentar el horno a 180°C. Colocar los filetes de salmón en una bandeja. Rociar con aceite de oliva, jugo de medio limón, sal, pimienta y eneldo picado. Hornear por 15-20 minutos o hasta que esté bien cocido. 2. Cocer Vegetales: Cocer al vapor el brócoli durante 5 minutos para que quede crujiente. 3. Montaje: En un bol grande, mezclar las espinacas frescas con los arándanos, las fresas cortadas y las nueces. Colocar el salmón y el brócoli al lado de la ensalada de berries.', 'Alto en Ácidos Grasos Omega-3 (salmón), esenciales para la salud cardiovascular y cerebral. Fuente significativa de fibra (vegetales y berries) y antioxidantes (arándanos y fresas). Es una comida completa, baja en carbohidratos netos y rica en proteínas de alto valor biológico. Ideal para el control de peso y dietas antiinflamatorias.', NULL, '2025-11-17 23:29:21', NULL),
(2, 5, 'Curry de Lentejas Rojas y Kale (Vegano)', 'Lentejas rojas secas (250g), Leche de coco de lata (400ml), Caldo de verduras (500ml), Jengibre fresco (2cm), Cúrcuma en polvo, Curry en polvo, Aceite de oliva, 1 Cebolla, 2 Zanahorias, 1 pimiento rojo, Kale (col rizada) (100g), Cilantro fresco para decorar, Sal y pimienta.', 5, '1. Sofrito: Picar la cebolla, el jengibre y el pimiento. En una olla, calentar aceite de oliva y sofreír la cebolla hasta que esté transparente. Añadir las especias (cúrcuma y curry) y cocinar por 1 minuto. 2. Cocer Lentejas: Añadir las lentejas rojas y la zanahoria en cubos a la olla. Cubrir con la leche de coco y el caldo de verduras. Dejar que hierva, luego reducir el fuego y cocinar a fuego lento por 20 minutos. 3. Finalizar: Incorporar las hojas de kale picadas y cocinar por 5 minutos más hasta que estén tiernas. Sazonar con sal y pimienta. Servir caliente y decorar con cilantro fresco.', 'Excelente fuente de proteína vegetal completa y hierro (lentejas), lo que lo hace ideal para dietas vegetarianas y veganas. Alto contenido de fibra dietética para la salud intestinal. El kale y la cúrcuma aportan compuestos antiinflamatorios y altos niveles de vitaminas A y K. Se sugiere acompañar con una porción de arroz integral para una mejor absorción de nutrientes.', 'assets/images/recetas/receta_5_20251118_064808_ce425c31.webp', '2025-11-17 23:48:08', NULL),
(3, 7, 'Curry de Lentejas Rojas y Kale (Vegano)', 'arroz', 5, 'ninguna', 'a', NULL, '2025-11-18 22:26:09', NULL),
(4, 9, 'no', 'no tiene', 2, 'tampoco', '12', NULL, '2025-11-18 22:27:57', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retroalimentacion`
--

CREATE TABLE `retroalimentacion` (
  `id` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `id_nutricionista` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `notificar` tinyint(1) DEFAULT 0,
  `creado_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `retroalimentacion`
--

INSERT INTO `retroalimentacion` (`id`, `id_pacientes`, `id_nutricionista`, `comentario`, `notificar`, `creado_at`) VALUES
(1, 8, 5, 'mas cebolla', 1, '2025-11-08 22:04:02'),
(2, 5, 5, 'no tome mucha pepsi', 0, '2025-11-08 22:09:08'),
(3, 8, 5, 'mas agua', 0, '2025-11-08 22:09:40'),
(4, 5, 5, 'tome mas agua', 0, '2025-11-08 22:12:37'),
(5, 5, 5, 'x', 0, '2025-11-09 18:11:37'),
(6, 1, 9, 'x', 0, '2025-11-10 21:32:15'),
(7, 5, 18, 'no coma eso', 0, '2025-11-14 20:49:06'),
(8, 10, 17, 'comentario', 0, '2025-11-18 18:20:11'),
(9, 10, 17, 'x', 0, '2025-11-18 18:20:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuarios` int(15) NOT NULL,
  `Nombre_completo` varchar(50) NOT NULL,
  `Correo_electronico` varchar(50) NOT NULL,
  `Usuario` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL,
  `rol` enum('Medico','Paciente','Administrador') NOT NULL DEFAULT 'Paciente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuarios`, `Nombre_completo`, `Correo_electronico`, `Usuario`, `Contrasena`, `rol`) VALUES
(1, 'Damaris Bonilla Garcia', 'Dambg@gmail.com', 'Damaris Bonilla G', '$2y$10$SPA6cFDoZ7RC/DgE5PJOheZ3rG7h/taCA9uRbG6e7NAU0IDHCwtdW', 'Medico'),
(2, 'Jose Levi Canales', 'canaleslevi@hotmail.com', 'Levi Canales', '$2y$10$RsWgM5yK7EInM92pAjfnae7wr86PaHUaO8oi5Q.Ek96CMMdh6XQQe', 'Medico'),
(3, 'Juan Perez', 'Jperez30@gmail.com', 'J Perez', '$2y$10$81Yn2vE2K4QflfgSeK2Eb.yh/ThDlGcECAQxT45Tt205D7HENpfK2', 'Medico'),
(4, 'Evelenth Garcia', 'Eygarcia@gmail.com', 'E Garcia', '$2y$10$NCVgVAoAz40berzOj0cUR.yaXRX6tgmtnwRS1dEiHLJIHWpsbkPKu', 'Medico'),
(5, 'Jazel Bonilla', 'gjazelbonilla@gmail.com', 'G Jazel Bonilla', '$2y$10$ubreqJoa9ejIWJyw98/cBO/mAGHDjaI/Vyg2OKv4mZ6qjahrkEb8C', 'Medico'),
(6, 'Lizbeth Dominguez', 'lizd@gmail.com', 'Lizbeth D', '$2y$10$z6sy5o8yGEtwxNoZq9aqLePBdEfVsI5B1B4S38.IvRFlY/.9jtMyO', 'Medico'),
(7, 'Arnold Cruz', 'cruz01@gmail.com', 'Arnold Cruz B', '$2y$10$AiBaVE0Ef.j3vu.gfAHlUeZrWVT4IE8bRjZmaPTuxTI.NlOWN08gm', 'Medico'),
(8, 'Luli Garcia', 'lugarci01@gmail.com', 'luli', '$2y$10$s0TUmmufgCLn/cvH2SLzDesoVOGcyTL/wWhDABB6LUdyu1vjKNv5i', 'Medico'),
(9, 'anthony', 'a@a.mx', 'anthony', '$2y$10$.P3ARwO3S/9ggXL0fNhy/.mTj/1jJQyGtTd04wcO10XIhyLAeZkOm', 'Medico'),
(10, 'kaleth', 'k@a.mx', 'Kaleth', '$2y$10$S4dPMAj3/ZWu7fM1Lu8F9uWgET8oqREJKu2iV9cTdbHwHzMZRdZIq', 'Medico'),
(11, 'prueba', 'prueba@c.com', 'prueba', '$2y$10$0y3d5/ao2gKO98P3ny7Uk.1FjE1q7p/Uf8JMpyPbx4QUYCAdsYoQq', 'Medico'),
(12, 'a', 'a@a.com', 'a', '$2y$10$9960JcPoRO0avYatpjfG5e1lCgJNbfNc.6ouLhjYLf/.gnflAvl/G', 'Paciente'),
(13, 'leonel messi', 'messi@gmail.com', 'campeon', '$2y$10$uQ6XY7hUPPd0m37l28hNi.j3OLzfnaD0tKiJvsVUs15/Jx8KwtYae', 'Medico'),
(14, 'ba', 'ba@c.com', 'chocolate', '$2y$10$JJ3tdsGongFs2j933EKkhOj57q5Bp9MpssVlPteIENY6e2PWQzVPG', 'Medico'),
(15, 'admin', 'admin@correo.com', 'admin', '$2y$10$47nh09.oqmX6Wbf1ayRyF.8G0rH6eZxZSRZtaiEoxTngs8p8004yG', 'Administrador'),
(17, 'paciente11', 'paciente11@correo.com', 'paciente11', '$2y$10$X/amdta3MMv/up/wBoqYpO1GoNfvC1ibHhCTvEbBhIXcl3KfdliPG', 'Paciente'),
(18, 'paciente12', 'paciente12@correo.com', 'paciente12', '$2y$10$8BikHqKvCN9Wh3XUC9l5vORmkgFJQN.UPd8Yp/odnlcX38S4EKTNq', 'Paciente'),
(19, 'jorge hernandez', 'j@correo.com', 'jorge', '$2y$10$PgQa1cbKq54lkTdoM5/MvuNpwXwr82EQYGaffprd7qHGPezBrmVrq', 'Paciente'),
(20, 'mario hernandez', 'm@correo.com', 'mario', '$2y$10$jWoE3ek3LQAohETpb4LbgOhkDeE4cPJJMqT9v7IlcR8ySvhisREhG', 'Medico');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alimentos_nutricionales`
--
ALTER TABLE `alimentos_nutricionales`
  ADD PRIMARY KEY (`id_alimento`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indices de la tabla `alimentos_registro`
--
ALTER TABLE `alimentos_registro`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_paciente_fecha` (`id_pacientes`,`fecha`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cita` (`medico_id`,`fecha`,`hora`);

--
-- Indices de la tabla `disponibilidades`
--
ALTER TABLE `disponibilidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`medico_id`,`fecha`,`hora`);

--
-- Indices de la tabla `ejercicios`
--
ALTER TABLE `ejercicios`
  ADD PRIMARY KEY (`id_ejercicio`);

--
-- Indices de la tabla `expediente`
--
ALTER TABLE `expediente`
  ADD PRIMARY KEY (`id_expediente`),
  ADD KEY `id_pacientes` (`id_pacientes`);

--
-- Indices de la tabla `historial_actualizaciones`
--
ALTER TABLE `historial_actualizaciones`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_usuarios` (`id_usuarios`),
  ADD KEY `actualizado_por` (`actualizado_por`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_pacientes`),
  ADD KEY `id_usuarios` (`id_usuarios`);

--
-- Indices de la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_paciente` (`id_pacientes`),
  ADD KEY `idx_medico` (`id_medico`);

--
-- Indices de la tabla `retroalimentacion`
--
ALTER TABLE `retroalimentacion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuarios`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alimentos_nutricionales`
--
ALTER TABLE `alimentos_nutricionales`
  MODIFY `id_alimento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `alimentos_registro`
--
ALTER TABLE `alimentos_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `disponibilidades`
--
ALTER TABLE `disponibilidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `ejercicios`
--
ALTER TABLE `ejercicios`
  MODIFY `id_ejercicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `expediente`
--
ALTER TABLE `expediente`
  MODIFY `id_expediente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `historial_actualizaciones`
--
ALTER TABLE `historial_actualizaciones`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_pacientes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `recetas`
--
ALTER TABLE `recetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `retroalimentacion`
--
ALTER TABLE `retroalimentacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuarios` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `historial_actualizaciones`
--
ALTER TABLE `historial_actualizaciones`
  ADD CONSTRAINT `historial_actualizaciones_ibfk_1` FOREIGN KEY (`id_usuarios`) REFERENCES `usuarios` (`id_usuarios`),
  ADD CONSTRAINT `historial_actualizaciones_ibfk_2` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuarios`);

--
-- Filtros para la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`id_usuarios`) REFERENCES `usuarios` (`id_usuarios`);

--
-- Filtros para la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD CONSTRAINT `fk_recetas_paciente` FOREIGN KEY (`id_pacientes`) REFERENCES `pacientes` (`id_pacientes`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
