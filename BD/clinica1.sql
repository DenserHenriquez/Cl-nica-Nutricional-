-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-03-2026 a las 05:59:24
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
(3, 'ensalada', 'plato', 20.00, 5.00, 2.00, 3.00, 1, '2025-11-18 22:44:39'),
(4, 'Huevo Cocido', 'alimento', 5.00, 10.00, 0.00, 0.00, 20, '2025-11-26 16:24:00'),
(5, 'Ensalada de pollo con vegetales', 'plato', 10.00, 15.00, 0.00, 2.00, 22, '2025-11-27 16:54:23');

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
(13, 13, '2025-11-26', 'almuerzo', 'Ensalada de pollo', '12:00:00', 'assets/images/alimentos/paciente_1320251126_23245106cd092e.jpg', '2025-11-26 16:24:51'),
(14, 15, '2025-11-27', 'cena', 'Ensalada', '20:00:00', 'assets/images/alimentos/paciente_1520251128_00002838bf6dec.jpg', '2025-11-27 17:00:28'),
(15, 15, '2026-02-24', 'cena', 'Pasta con vegetales', '19:00:00', 'assets/images/alimentos/paciente_1520260224_064408222ba959.jpg', '2026-02-23 23:44:08'),
(16, 20, '2026-03-09', 'snack', 'pizza', '23:08:00', 'assets/images/alimentos/paciente_2020260309_060829b116ad65.webp', '2026-03-08 23:08:29');

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
(1, 2, 'Jose Santos Bonilla', '2025-11-25', '10:00:00', 'Control', 'confirmada', NULL, '2025-11-25 00:44:39'),
(2, 2, 'LEO', '2025-11-27', '17:00:00', 'Control', 'confirmada', NULL, '2025-11-27 22:48:02'),
(3, 22, 'LEO', '2026-02-23', '08:30:00', 'Control', 'confirmada', NULL, '2026-02-24 04:40:51'),
(4, 22, 'LEO', '2026-02-24', '12:30:00', 'Control', 'confirmada', NULL, '2026-02-24 06:06:26'),
(5, 22, 'Jose Santos Bonilla', '2026-02-24', '17:30:00', 'CONTROL', 'confirmada', NULL, '2026-02-24 22:21:12'),
(6, 22, 'Jose Santos Bonilla', '2026-02-25', '17:00:00', 'Control', 'confirmada', NULL, '2026-02-25 21:56:44'),
(7, 22, 'LEO', '2026-02-25', '19:00:00', 'Control', 'confirmada', NULL, '2026-02-25 21:57:15'),
(8, 22, 'Jose Santos Bonilla', '2026-02-26', '18:00:00', 'Control', 'confirmada', NULL, '2026-02-26 21:57:36'),
(9, 9, 'Denser', '2026-03-07', '09:00:00', 'SOBREPESO', 'confirmada', NULL, '2026-03-07 01:48:46'),
(10, 9, 'David Pacheco', '2026-03-07', '10:30:00', 'Control de peso', 'confirmada', 27, '2026-03-07 05:45:38'),
(11, 9, 'Fernanda Hola', '2026-03-07', '12:00:00', 'Control de Peso', 'confirmada', 28, '2026-03-08 03:46:49'),
(12, 9, 'Genesis 2026', '2026-03-07', '10:00:00', 'Seguimiento de peso', 'confirmada', 29, '2026-03-08 03:58:37'),
(13, 9, 'Anthony paciente', '2026-03-07', '11:30:00', 'comprobación', 'completada', 30, '2026-03-09 05:06:59'),
(14, 9, 'Anthony paciente', '2026-03-08', '10:00:00', 'revision de medico', 'confirmada', 30, '2026-03-09 18:54:15'),
(15, 7, 'Anthony paciente', '2026-03-08', '09:00:00', 'repeticion', 'pendiente', 30, '2026-03-09 18:54:36'),
(16, 9, 'Anthony paciente', '2026-03-08', '12:00:00', 'emergencia nutricional', 'confirmada', 30, '2026-03-09 19:34:09'),
(17, 9, 'Anthony paciente', '2026-03-08', '08:00:00', 'sin motivos', 'confirmada', 30, '2026-03-10 01:19:54'),
(18, 9, 'Anthony paciente', '2026-03-08', '07:00:00', 'pruebas', 'confirmada', 30, '2026-03-10 01:31:24'),
(19, 9, 'Anthony Kalth', '2026-03-08', '13:00:00', 'Control de peso', 'pendiente', 31, '2026-03-10 04:40:12'),
(20, 9, 'Ana Gabriela Nolasco', '2026-03-08', '14:00:00', 'Control de Peso', 'confirmada', 32, '2026-03-22 03:49:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultas_medicas`
--

CREATE TABLE `consultas_medicas` (
  `id` int(11) NOT NULL,
  `medico_id` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `peso` decimal(10,2) DEFAULT NULL,
  `estatura` decimal(10,2) DEFAULT NULL,
  `edad_metabolica` decimal(10,2) DEFAULT NULL,
  `imc` decimal(10,2) DEFAULT NULL,
  `masa_muscular` decimal(10,2) DEFAULT NULL,
  `musculo_esqueletico` decimal(10,2) DEFAULT NULL,
  `grasa_corporal` decimal(10,2) DEFAULT NULL,
  `grasa_visceral` decimal(10,2) DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `consultas_medicas`
--

INSERT INTO `consultas_medicas` (`id`, `medico_id`, `paciente_id`, `fecha`, `peso`, `estatura`, `edad_metabolica`, `imc`, `masa_muscular`, `musculo_esqueletico`, `grasa_corporal`, `grasa_visceral`, `motivo`, `notas`, `created_at`) VALUES
(1, 15, 17, '2026-03-04 22:01:01', 85.00, 175.00, 28.00, 27.76, 98.00, NULL, NULL, NULL, 'Control de peso', 'El paciente subio de masa muscular, se tiene que mandar a realizar examenes, para ver el motivo, se le deja dieta, ejercicio de caminar y poder tener alguna cita con su psicologo', '2026-03-07 04:01:01'),
(2, 15, 17, '2026-03-02 22:10:43', 86.00, 175.00, 35.00, 28.08, 99.00, NULL, NULL, NULL, 'control de peso', 'Paciente aumento de peso, se tiene tiene que complementar con medicamento por diabetes', '2026-03-07 04:10:43'),
(3, 15, 17, '2026-03-06 22:28:42', 93.00, 175.00, 40.00, 30.37, 100.00, NULL, NULL, NULL, 'control de peso', 'Paciente evaluado nuevamente, no hace dieta, no hace ejercicio. quiere estar solo con medicamento. pero no esta funcionando', '2026-03-07 04:28:42'),
(4, 15, 17, '2026-03-06 23:02:46', 84.00, 175.00, 85.00, 27.43, 98.00, NULL, NULL, NULL, 'control de peso', 'control', '2026-03-07 05:02:46'),
(5, 15, 7, '2026-03-08 20:49:04', 140.00, NULL, 18.00, NULL, 44.00, NULL, NULL, NULL, 'ninguna', '', '2026-03-09 02:49:04'),
(6, 9, 17, '2026-03-17 21:00:43', 58.00, 175.00, 58.00, 18.94, 60.00, NULL, NULL, NULL, 'Control de peso', 'ninguno', '2026-03-18 03:00:43'),
(7, 15, 17, '2026-03-20 21:31:34', 58.00, 175.00, 40.00, 18.94, 85.00, NULL, NULL, NULL, 'Seguimiento', 'Ningun', '2026-03-21 03:31:34'),
(8, 15, 17, '2026-03-20 22:54:26', NULL, 175.00, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '2026-03-21 04:54:26'),
(9, 15, 17, '2026-03-20 22:54:52', 75.00, 175.00, 35.00, 24.49, NULL, 25.00, 35.00, 40.00, 'Seguimiento', 'n/a', '2026-03-21 04:54:52'),
(10, 15, 17, '2026-03-20 23:09:44', 75.00, 175.00, 45.00, 24.49, NULL, 20.00, 35.00, 45.00, 'Seguimiento', 'ninguno', '2026-03-21 05:09:44'),
(11, 9, 21, '2026-03-21 22:03:51', 65.00, 169.00, 35.00, 22.76, NULL, 40.00, 42.00, 28.00, 'Aumento de peso repentino', 'Se procede a evaluar a la paciente, se remite a realizarse estudios hemograma, trigliceridos, colesterol (todos), Glucosa. \r\nVerificar en una semana el avance', '2026-03-22 04:03:51');

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
(1, 1, '2025-11-19', '13:30:00', 'libre'),
(2, 1, '2025-11-19', '14:00:00', 'libre'),
(3, 2, '2025-11-25', '08:00:00', 'libre'),
(4, 2, '2025-11-25', '09:00:00', 'libre'),
(5, 2, '2025-11-25', '10:00:00', 'bloqueado'),
(6, 2, '2025-11-25', '11:00:00', 'libre'),
(7, 1, '2025-11-25', '08:00:00', 'libre'),
(8, 1, '2025-11-25', '09:00:00', 'libre'),
(9, 1, '2025-11-25', '10:00:00', 'libre'),
(10, 1, '2025-11-25', '11:00:00', 'libre'),
(11, 1, '2025-11-25', '12:00:00', 'libre'),
(12, 1, '2025-11-25', '13:00:00', 'libre'),
(13, 1, '2025-11-25', '14:00:00', 'libre'),
(14, 1, '2025-11-26', '12:00:00', 'libre'),
(15, 1, '2025-11-26', '13:00:00', 'libre'),
(16, 1, '2025-11-26', '14:00:00', 'libre'),
(17, 1, '2025-11-26', '15:00:00', 'libre'),
(18, 1, '2025-11-26', '16:00:00', 'libre'),
(19, 3, '2025-11-26', '10:00:00', 'libre'),
(20, 3, '2025-11-26', '11:00:00', 'libre'),
(21, 3, '2025-11-26', '12:00:00', 'libre'),
(22, 3, '2025-11-26', '13:00:00', 'libre'),
(23, 3, '2025-11-26', '14:00:00', 'libre'),
(24, 4, '2025-11-25', '08:00:00', 'libre'),
(25, 4, '2025-11-25', '09:00:00', 'libre'),
(26, 4, '2025-11-25', '10:00:00', 'libre'),
(27, 4, '2025-11-25', '11:00:00', 'libre'),
(28, 2, '2025-11-26', '10:00:00', 'libre'),
(29, 2, '2025-11-26', '11:00:00', 'libre'),
(30, 2, '2025-11-26', '12:00:00', 'libre'),
(31, 2, '2025-11-26', '13:00:00', 'libre'),
(32, 2, '2025-11-26', '14:00:00', 'libre'),
(33, 2, '2025-11-26', '15:00:00', 'libre'),
(34, 2, '2025-11-26', '16:00:00', 'libre'),
(35, 2, '2025-11-26', '17:00:00', 'libre'),
(36, 4, '2025-11-26', '12:00:00', 'libre'),
(37, 4, '2025-11-26', '13:00:00', 'libre'),
(38, 4, '2025-11-26', '14:00:00', 'libre'),
(39, 4, '2025-11-26', '15:00:00', 'libre'),
(40, 4, '2025-11-26', '16:00:00', 'libre'),
(41, 4, '2025-11-26', '17:00:00', 'libre'),
(42, 2, '2025-11-27', '12:00:00', 'libre'),
(43, 2, '2025-11-27', '13:00:00', 'libre'),
(44, 2, '2025-11-27', '14:00:00', 'libre'),
(45, 2, '2025-11-27', '15:00:00', 'libre'),
(46, 2, '2025-11-27', '16:00:00', 'libre'),
(47, 2, '2025-11-27', '17:00:00', 'bloqueado'),
(48, 2, '2025-11-28', '13:00:00', 'libre'),
(49, 2, '2025-11-28', '14:00:00', 'libre'),
(50, 2, '2025-11-28', '15:00:00', 'libre'),
(51, 2, '2025-11-28', '16:00:00', 'libre'),
(52, 2, '2025-11-28', '17:00:00', 'libre'),
(53, 22, '2026-02-23', '08:00:00', 'libre'),
(54, 22, '2026-02-23', '08:30:00', 'bloqueado'),
(55, 22, '2026-02-23', '09:00:00', 'libre'),
(56, 22, '2026-02-23', '09:30:00', 'libre'),
(57, 22, '2026-02-23', '10:00:00', 'libre'),
(58, 22, '2026-02-23', '10:30:00', 'libre'),
(59, 22, '2026-02-23', '11:00:00', 'libre'),
(60, 22, '2026-02-23', '11:30:00', 'libre'),
(61, 22, '2026-02-23', '12:00:00', 'libre'),
(62, 22, '2026-02-24', '12:00:00', 'libre'),
(63, 22, '2026-02-24', '12:30:00', 'bloqueado'),
(64, 22, '2026-02-24', '13:00:00', 'libre'),
(65, 22, '2026-02-24', '13:30:00', 'libre'),
(66, 22, '2026-02-24', '14:00:00', 'libre'),
(67, 22, '2026-02-24', '14:30:00', 'libre'),
(68, 22, '2026-02-24', '15:00:00', 'libre'),
(69, 22, '2026-02-24', '15:30:00', 'libre'),
(70, 22, '2026-02-24', '16:00:00', 'libre'),
(71, 22, '2026-02-24', '16:30:00', 'libre'),
(72, 22, '2026-02-24', '17:00:00', 'libre'),
(73, 22, '2026-02-24', '17:30:00', 'bloqueado'),
(74, 22, '2026-02-25', '15:00:00', 'libre'),
(75, 22, '2026-02-25', '15:30:00', 'libre'),
(76, 22, '2026-02-25', '16:00:00', 'libre'),
(77, 22, '2026-02-25', '16:30:00', 'libre'),
(78, 22, '2026-02-25', '17:00:00', 'bloqueado'),
(79, 22, '2026-02-25', '17:30:00', 'libre'),
(80, 22, '2026-02-25', '18:00:00', 'libre'),
(81, 22, '2026-02-25', '18:30:00', 'libre'),
(82, 22, '2026-02-25', '19:00:00', 'bloqueado'),
(83, 22, '2026-02-25', '19:30:00', 'libre'),
(84, 22, '2026-02-26', '15:00:00', 'libre'),
(85, 22, '2026-02-26', '15:30:00', 'libre'),
(86, 22, '2026-02-26', '16:00:00', 'libre'),
(87, 22, '2026-02-26', '16:30:00', 'libre'),
(88, 22, '2026-02-26', '17:00:00', 'libre'),
(89, 22, '2026-02-26', '17:30:00', 'libre'),
(90, 22, '2026-02-26', '18:00:00', 'bloqueado'),
(91, 22, '2026-02-26', '18:30:00', 'libre'),
(92, 22, '2026-02-26', '19:00:00', 'libre'),
(93, 22, '2026-02-26', '19:30:00', 'libre'),
(94, 9, '2026-03-07', '09:00:00', 'bloqueado'),
(95, 9, '2026-03-07', '09:30:00', 'libre'),
(96, 9, '2026-03-07', '10:00:00', 'bloqueado'),
(97, 9, '2026-03-07', '10:30:00', 'bloqueado'),
(98, 9, '2026-03-07', '11:00:00', 'libre'),
(99, 9, '2026-03-07', '11:30:00', 'bloqueado'),
(100, 9, '2026-03-07', '12:00:00', 'bloqueado'),
(101, 9, '2026-03-07', '12:30:00', 'libre'),
(102, 9, '2026-03-07', '13:00:00', 'libre'),
(103, 9, '2026-03-07', '13:30:00', 'libre'),
(104, 9, '2026-03-07', '14:00:00', 'libre'),
(105, 9, '2026-03-07', '14:30:00', 'libre'),
(106, 9, '2026-03-07', '15:00:00', 'libre'),
(107, 9, '2026-03-07', '15:30:00', 'libre'),
(108, 9, '2026-03-07', '16:00:00', 'libre'),
(109, 9, '2026-03-07', '16:30:00', 'libre'),
(110, 9, '2026-03-07', '17:00:00', 'libre'),
(111, 9, '2026-03-07', '17:30:00', 'libre'),
(112, 9, '2026-03-07', '18:00:00', 'libre'),
(113, 9, '2026-03-07', '18:30:00', 'libre'),
(114, 9, '2026-03-07', '19:00:00', 'libre'),
(115, 9, '2026-03-07', '19:30:00', 'libre'),
(116, 9, '2026-03-07', '20:00:00', 'libre'),
(117, 9, '2026-03-07', '20:30:00', 'libre'),
(118, 9, '2026-03-08', '07:00:00', 'bloqueado'),
(119, 9, '2026-03-08', '08:00:00', 'bloqueado'),
(120, 9, '2026-03-08', '09:00:00', 'libre'),
(121, 9, '2026-03-08', '10:00:00', 'bloqueado'),
(122, 9, '2026-03-08', '11:00:00', 'libre'),
(123, 9, '2026-03-08', '12:00:00', 'bloqueado'),
(124, 9, '2026-03-08', '13:00:00', 'bloqueado'),
(125, 9, '2026-03-08', '14:00:00', 'bloqueado'),
(126, 9, '2026-03-08', '15:00:00', 'libre'),
(127, 9, '2026-03-08', '16:00:00', 'libre'),
(128, 9, '2026-03-08', '17:00:00', 'libre'),
(129, 9, '2026-03-08', '18:00:00', 'libre'),
(130, 9, '2026-03-08', '19:00:00', 'libre'),
(131, 7, '2026-03-08', '06:00:00', 'libre'),
(132, 7, '2026-03-08', '07:00:00', 'libre'),
(133, 7, '2026-03-08', '08:00:00', 'libre'),
(134, 7, '2026-03-08', '09:00:00', 'bloqueado'),
(135, 7, '2026-03-08', '10:00:00', 'libre'),
(136, 7, '2026-03-08', '11:00:00', 'libre'),
(137, 7, '2026-03-08', '12:00:00', 'libre');

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
(6, 13, 'Caminata', 30, '13:00:00', '2025-11-26', 'uploads/ejercicios/paciente_13_20251126_232648_f20c42.jpg', 'Hice la caminata trotando', '2025-11-26 22:26:48'),
(7, 15, 'Caminata', 30, '13:00:00', '2025-11-27', 'uploads/ejercicios/paciente_15_20251128_000214_867ec8.jpg', 'Caminata a paso lento', '2025-11-27 23:02:14'),
(8, 15, 'Ciclismo', 30, '08:00:00', '2026-02-24', 'uploads/ejercicios/paciente_15_20260224_064651_494cfe.jpg', 'Divertido', '2026-02-24 05:46:51'),
(9, 20, 'Otro', 60, '23:00:00', '2026-03-09', 'uploads/ejercicios/paciente_20_20260309_061310_502bb4.jpg', 'dormir', '2026-03-09 05:13:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `examenes`
--

CREATE TABLE `examenes` (
  `id` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion_paciente` varchar(50) NOT NULL,
  `tamano` int(11) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `categoria` varchar(100) NOT NULL DEFAULT 'Otros / Especializados',
  `nombre_examen` varchar(255) NOT NULL DEFAULT '',
  `fecha_examen` date NOT NULL DEFAULT curdate(),
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `examenes`
--

INSERT INTO `examenes` (`id`, `id_pacientes`, `nombre_paciente`, `ruta`, `tipo`, `descripcion_paciente`, `tamano`, `creado_en`, `categoria`, `nombre_examen`, `fecha_examen`, `notas`) VALUES
(1, 15, 'Mapa_mental_estad__sticas_descriptivas.pdf', 'uploads/examenes/examen_15_20260224_222012_9559b510.pdf', 'pdf', 'Examen de laboratorio', 242513, '2026-02-24 15:20:12', 'Otros / Especializados', '', '2026-03-06', NULL),
(2, 13, 'SPRINT_5-_informe-product-backlog.pdf', 'uploads/examenes/examen_13_20260226_231007_32b65e6e.pdf', 'pdf', 'Examen de laboratorio', 470916, '2026-02-26 16:10:07', 'Otros / Especializados', '', '2026-03-06', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expediente`
--

CREATE TABLE `expediente` (
  `id_expediente` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `edad_metabolica` decimal(10,2) DEFAULT NULL,
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

INSERT INTO `expediente` (`id_expediente`, `id_pacientes`, `edad_metabolica`, `peso`, `estatura`, `IMC`, `masa_muscular`, `enfermedades_base`, `medicamentos`, `fecha_registro`) VALUES
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
(17, 11, 160.00, 54.20, 160.00, 21.17, 48.00, 'Ninguna', 'Ninguno', '2025-11-19 04:49:51'),
(18, 12, 160.00, 60.00, 160.00, 23.44, 40.00, 'Ninguna', 'Ninguno', '2025-11-25 00:40:51'),
(19, 13, 165.00, 70.50, 165.00, 25.90, 50.50, 'N/A', 'N/A', '2025-11-25 00:47:52'),
(20, 14, 160.00, 50.00, 160.00, 19.53, 40.00, 'N/A', 'N/A', '2025-11-26 22:39:17'),
(21, 15, 160.00, 55.00, 160.00, 21.48, 45.00, 'Ninguna', 'Ninguno', '2025-11-27 22:53:16'),
(22, 16, 170.50, 70.50, 170.50, 24.25, 50.50, 'Ninguna', 'Ninguno', '2026-02-26 22:08:44'),
(23, 17, 158.00, 58.50, 170.00, 20.24, 35.00, 'NINIGUNA', 'NINGUNO', '2026-03-07 01:44:15'),
(24, 18, 40.00, 85.00, 160.00, 33.20, 35.00, 'NINGUNA', 'NINGUNO', '2026-03-07 05:15:35'),
(25, 11, 30.00, 54.20, 160.00, 21.17, 48.00, 'Ninguna', 'Ninguno', '2026-03-07 05:21:00'),
(26, 19, 38.00, 90.00, 180.00, 27.78, 50.00, 'ninguna', 'ninguna', '2026-03-07 05:51:54'),
(27, 20, 35.00, 70.00, 171.00, 23.94, 56.00, 'probador compulsivo', 'X', '2026-03-09 05:05:33'),
(28, 5, 16.00, 74.00, 172.01, 25.01, 55.01, 'a', 'b', '2026-03-09 19:39:34'),
(29, 11, 19.00, 54.20, 160.00, 21.17, 48.00, 'Ninguna', 'Ninguno', '2026-03-09 19:59:56'),
(30, 11, 20.00, 54.20, 160.00, 21.17, 48.00, 'Ninguna', 'Ninguno', '2026-03-09 20:00:39'),
(31, 20, 35.00, 65.00, 171.00, 22.23, 56.00, 'probador compulsivo', 'X', '2026-03-09 20:12:03'),
(32, 20, 35.00, 66.00, 171.00, 22.57, 56.00, 'probador compulsivo', 'X', '2026-03-09 20:13:07'),
(33, 21, 28.00, 65.00, 169.00, 22.76, NULL, 'Alergia', 'Cetirizina.', '2026-03-22 03:47:18');

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
(21, 9, 'medicamentos', NULL, 'b', 9, '2025-11-11 03:32:57'),
(22, 23, 'Contrasena', '$2y$10$kdFT2PcdKrQ.Rhsx7fq3XOBoni4Fuc0/KblDo0GP.UI/N/sINSzya', '$2y$10$QfhM0ijUfTI9Wto7Qp.6k.RwCkOBIGNtnM6T246aMZ7aL5qUBZiMK', 23, '2025-11-27 23:03:48'),
(23, 23, 'telefono', '98395671', '33456012', 23, '2025-11-27 23:03:48'),
(24, 19, 'Contrasena', '[PROTEGIDO]', '[PROTEGIDO]', 19, '2026-02-24 21:25:48'),
(25, 22, 'Contrasena', '[PROTEGIDO]', '[PROTEGIDO]', 22, '2026-02-26 22:12:44'),
(26, 15, 'edad_metabolica', NULL, '30', 15, '2026-03-07 05:21:00'),
(27, 15, 'peso', NULL, '54.20', 15, '2026-03-07 05:21:00'),
(28, 15, 'estatura', NULL, '160.00', 15, '2026-03-07 05:21:00'),
(29, 15, 'IMC', NULL, '21.17', 15, '2026-03-07 05:21:00'),
(30, 15, 'masa_muscular', NULL, '48.00', 15, '2026-03-07 05:21:00'),
(31, 15, 'enfermedades_base', NULL, 'Ninguna', 15, '2026-03-07 05:21:00'),
(32, 15, 'medicamentos', NULL, 'Ninguno', 15, '2026-03-07 05:21:00'),
(33, 9, 'edad_metabolica', NULL, '16', 9, '2026-03-09 19:39:34'),
(34, 9, 'peso', NULL, '74', 9, '2026-03-09 19:39:34'),
(35, 9, 'estatura', NULL, '172.01', 9, '2026-03-09 19:39:34'),
(36, 9, 'IMC', NULL, '25.01', 9, '2026-03-09 19:39:34'),
(37, 9, 'masa_muscular', NULL, '55.01', 9, '2026-03-09 19:39:34'),
(38, 9, 'enfermedades_base', NULL, 'a', 9, '2026-03-09 19:39:34'),
(39, 9, 'medicamentos', NULL, 'b', 9, '2026-03-09 19:39:34'),
(40, 15, 'edad_metabolica', NULL, '19.00', 15, '2026-03-09 19:59:56'),
(41, 15, 'peso', NULL, '54.20', 15, '2026-03-09 19:59:56'),
(42, 15, 'estatura', NULL, '160.00', 15, '2026-03-09 19:59:56'),
(43, 15, 'IMC', NULL, '21.17', 15, '2026-03-09 19:59:56'),
(44, 15, 'masa_muscular', NULL, '48.00', 15, '2026-03-09 19:59:56'),
(45, 15, 'enfermedades_base', NULL, 'Ninguna', 15, '2026-03-09 19:59:56'),
(46, 15, 'medicamentos', NULL, 'Ninguno', 15, '2026-03-09 19:59:56'),
(47, 15, 'edad_metabolica', NULL, '20.00', 15, '2026-03-09 20:00:39'),
(48, 15, 'peso', NULL, '54.20', 15, '2026-03-09 20:00:39'),
(49, 15, 'estatura', NULL, '160.00', 15, '2026-03-09 20:00:39'),
(50, 15, 'IMC', NULL, '21.17', 15, '2026-03-09 20:00:39'),
(51, 15, 'masa_muscular', NULL, '48.00', 15, '2026-03-09 20:00:39'),
(52, 15, 'enfermedades_base', NULL, 'Ninguna', 15, '2026-03-09 20:00:39'),
(53, 15, 'medicamentos', NULL, 'Ninguno', 15, '2026-03-09 20:00:39'),
(54, 30, 'edad_metabolica', NULL, '35.00', 9, '2026-03-09 20:12:03'),
(55, 30, 'peso', NULL, '65', 9, '2026-03-09 20:12:03'),
(56, 30, 'estatura', NULL, '171.00', 9, '2026-03-09 20:12:03'),
(57, 30, 'IMC', NULL, '22.23', 9, '2026-03-09 20:12:03'),
(58, 30, 'masa_muscular', NULL, '56.00', 9, '2026-03-09 20:12:03'),
(59, 30, 'enfermedades_base', NULL, 'probador compulsivo', 9, '2026-03-09 20:12:03'),
(60, 30, 'medicamentos', NULL, 'X', 9, '2026-03-09 20:12:03'),
(61, 30, 'edad_metabolica', NULL, '35.00', 9, '2026-03-09 20:13:07'),
(62, 30, 'peso', NULL, '66', 9, '2026-03-09 20:13:07'),
(63, 30, 'estatura', NULL, '171.00', 9, '2026-03-09 20:13:07'),
(64, 30, 'IMC', NULL, '22.57', 9, '2026-03-09 20:13:07'),
(65, 30, 'masa_muscular', NULL, '56.00', 9, '2026-03-09 20:13:07'),
(66, 30, 'enfermedades_base', NULL, 'probador compulsivo', 9, '2026-03-09 20:13:07'),
(67, 30, 'medicamentos', NULL, 'X', 9, '2026-03-09 20:13:07'),
(68, 15, 'Correo_electronico', 'admin@correo.com', 'denser@nutri.hn', 15, '2026-03-21 05:53:02'),
(69, 15, 'Contrasena', '$2y$10$47nh09.oqmX6Wbf1ayRyF.8G0rH6eZxZSRZtaiEoxTngs8p8004yG', '$2y$10$peq/6Ip7FJ1rOTm9za1SHe9e4rhkMo7lA9jqkVZTimabswssog0XK', 15, '2026-03-21 05:53:02'),
(70, 15, 'Correo_electronico', 'denser@nutri.hn', 'admin@correo.com', 15, '2026-03-21 05:56:42'),
(71, 15, 'Contrasena', '$2y$10$peq/6Ip7FJ1rOTm9za1SHe9e4rhkMo7lA9jqkVZTimabswssog0XK', '$2y$10$iVKvzwR/2NcOZw4A.sueee6cDzJC864aR62R6lFHCQaK8pXvtwiUK', 15, '2026-03-21 05:56:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inicio_banners`
--

CREATE TABLE `inicio_banners` (
  `id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL DEFAULT '',
  `subtitulo` text DEFAULT NULL,
  `btn_texto` varchar(100) DEFAULT NULL,
  `btn_link` varchar(400) DEFAULT NULL,
  `imagen` varchar(500) DEFAULT NULL,
  `bg_color` varchar(50) DEFAULT '#198754',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inicio_banners`
--

INSERT INTO `inicio_banners` (`id`, `titulo`, `subtitulo`, `btn_texto`, `btn_link`, `imagen`, `bg_color`, `orden`, `activo`, `created_at`) VALUES
(1, 'tu salud', 'salud extrema', '', '', 'assets/images/banners/banner_1773036626_4014a71f.jpg', '#198754', 0, 1, '2026-03-09 06:10:26'),
(2, 'tu dia', '', '', '', 'assets/images/banners/banner_1773036680_68a65222.jpg', '#198754', 1, 1, '2026-03-09 06:11:20'),
(3, 'Recetas personalizadas', 'recibe recetas de expertos', '', '', 'assets/images/banners/banner_1773090012_29c39e47.png', '#198754', 2, 1, '2026-03-09 21:00:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inicio_config`
--

CREATE TABLE `inicio_config` (
  `clave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inicio_config`
--

INSERT INTO `inicio_config` (`clave`, `valor`) VALUES
('carousel_interval', '5000'),
('pac_carousel_interval', '5000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inicio_tarjetas`
--

CREATE TABLE `inicio_tarjetas` (
  `id` int(11) NOT NULL,
  `icono` varchar(100) DEFAULT 'fa-star',
  `titulo` varchar(200) NOT NULL DEFAULT '',
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(500) DEFAULT NULL,
  `enlace` varchar(400) DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inicio_tarjetas`
--

INSERT INTO `inicio_tarjetas` (`id`, `icono`, `titulo`, `descripcion`, `imagen`, `enlace`, `orden`, `activo`, `created_at`) VALUES
(1, 'fa-star', 'Consultas nuticionales', 'haz tu consulta con profecionales', 'assets/images/tarjetas/tarjeta_1773076824_abe62354.jpg', '', 0, 1, '2026-03-09 17:20:14'),
(2, 'fa-utensils', 'Planes Alimenticios', 'Diseñamos dietas personalizadas según tus necesidades nutricionales, preferencias y objetivos de salud.', 'assets/images/tarjetas/tarjeta_1773077722_4f4b9185.jpg', '', 1, 1, '2026-03-09 17:35:22'),
(3, 'fa-weight', 'Control de Peso', 'Programas especializados para pérdida, ganancia o mantenimiento de peso de forma saludable.', '', '', 3, 1, '2026-03-09 17:36:47'),
(4, 'fa-heartbeat', 'Nutrición Clínica', 'Apoyo nutricional para enfermedades crónicas, alergias alimentarias y condiciones especiales.', NULL, '', 3, 1, '2026-03-09 17:37:34'),
(5, 'fa-running', 'Deporte y Fitness', 'Nutrición deportiva para atletas y personas activas que buscan optimizar su rendimiento.', NULL, '', 4, 1, '2026-03-09 17:38:08'),
(6, 'fa-baby', 'Nutrición Infantil', 'Asesoramiento para el crecimiento saludable de niños y adolescentes.', NULL, '', 5, 1, '2026-03-09 17:39:01'),
(7, 'fa-users', 'Consultas Familiares', 'Planes nutricionales para toda la familia, adaptados a diferentes edades y necesidades.', 'assets/images/tarjetas/tarjeta_1773078047_cd348ab5.webp', '', 2, 1, '2026-03-09 17:40:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id_pacientes` int(11) NOT NULL,
  `id_usuarios` int(11) NOT NULL,
  `nombre_completo` varchar(100) DEFAULT NULL,
  `sexo` enum('M','F') NOT NULL DEFAULT 'M',
  `referencia_medica` varchar(255) DEFAULT NULL,
  `DNI` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pacientes`
--

INSERT INTO `pacientes` (`id_pacientes`, `id_usuarios`, `nombre_completo`, `sexo`, `referencia_medica`, `DNI`, `fecha_nacimiento`, `edad`, `telefono`, `estado`) VALUES
(1, 1, 'Damaris  Bonilla Garcia', 'M', NULL, '0823200610125', '2005-08-01', 20, '33456012', 'Activo'),
(2, 2, 'Jose Levi Canales', 'M', NULL, '1705201000216', '2010-10-08', 15, '32653641', 'Activo'),
(3, 3, 'Juan David Perez ', 'M', NULL, '0801200015300', '2025-04-08', 25, '33452018', 'Inactivo'),
(4, 4, 'Evelenth Garcia', 'M', NULL, '0823200013256', '2000-07-06', 25, '33654892', 'Inactivo'),
(5, 9, 'anthony', 'M', NULL, '0801200012346', '2012-11-05', 13, '99887766', 'Inactivo'),
(6, 10, 'kaleth', 'M', NULL, '0801200012346', '2012-11-05', 13, '99887766', 'Inactivo'),
(7, 12, 'a', 'M', NULL, '0801200012348', '2025-06-18', 0, '99999911', 'Activo'),
(8, 13, 'leonel messi', 'M', NULL, '0801200012349', '2025-06-18', 0, '99999912', 'Inactivo'),
(9, 18, 'paciente12', 'M', NULL, '1234567891234', '2001-05-17', 24, '99999914', 'Activo'),
(10, 17, 'paciente11', 'M', NULL, '0823123451234', '2001-05-17', 24, '99999914', 'Activo'),
(11, 15, 'admin', 'M', NULL, '0826200010365', '2000-11-03', 25, '36458010', 'Activo'),
(12, 20, 'Benjamin Garcia', 'M', NULL, '0801200030158', '2000-08-12', 25, '99664433', 'Inactivo'),
(13, 19, 'Jose Santos Bonilla', 'M', NULL, '1707197400255', '1974-05-13', 51, '33665544', 'Activo'),
(14, 22, 'Genesis Garcia', 'M', NULL, '0823200000125', '2000-08-10', 25, '98465522', 'Activo'),
(15, 23, 'LEO', 'M', NULL, '0801200000125', '2000-03-01', 25, '33456012', 'Activo'),
(16, 25, 'Fernando Garcia', 'M', 'Walter Amaya', '0823199900182', '1999-02-01', 27, '98394657', 'Activo'),
(17, 26, 'Denser', 'M', 'Ninguno', '0801199712319', '1995-03-02', 31, '94456589', 'Activo'),
(18, 21, 'Kari Ruiz', 'M', 'Ninguno', '0801199712887', '1996-03-02', 30, '94456580', 'Activo'),
(19, 27, 'David Pacheco', 'M', 'Ninguno', '0801199712398', '1995-01-03', 31, '94456552', 'Activo'),
(20, 30, 'Anthony paciente', 'M', 'admin', '0801200215999', '2002-06-18', 23, '89494929', 'Activo'),
(21, 32, 'Ana Gabriela Nolasco', 'M', 'Ninguno', '0801199712317', '0000-00-00', 28, '94456590', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pac_banners`
--

CREATE TABLE `pac_banners` (
  `id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL DEFAULT '',
  `subtitulo` text DEFAULT NULL,
  `btn_texto` varchar(100) DEFAULT NULL,
  `btn_link` varchar(400) DEFAULT NULL,
  `imagen` varchar(500) DEFAULT NULL,
  `bg_color` varchar(50) DEFAULT '#198754',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pac_banners`
--

INSERT INTO `pac_banners` (`id`, `titulo`, `subtitulo`, `btn_texto`, `btn_link`, `imagen`, `bg_color`, `orden`, `activo`, `created_at`) VALUES
(1, 'Agenda tu cita', 'nuestros médicos te atenderán', 'Agendar', 'Disponibilidad_citas.php', 'assets/images/pac_banners/pac_1773089305_d954741b.png', '#198754', 0, 1, '2026-03-09 20:48:25'),
(2, 'revisa tu progreso', 'Mantente informado', 'panel', 'panelevolucionpaciente.php', 'assets/images/pac_banners/pac_1773089769_af26d760.png', '#198754', 1, 1, '2026-03-09 20:56:09'),
(3, 'Recetas personalizadas', 'recibe recetas de expertos', 'Recetas', 'Gestion_Receta.php', 'assets/images/pac_banners/pac_1773090261_8ebf1793.png', '#198754', 2, 1, '2026-03-09 21:03:11');

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
(7, 9, 'Batido Verde de Kiwi y Espinacas con Semillas de Chía', '1 taza de espinacas frescas, 2 kiwis (pelados), 1 banana madura (congelada si es posible), 1/2 taza de leche de almendra (o agua), 1 cucharada de semillas de chía, 1 cucharadita de miel o stevia (opcional).', 2, '1. Preparación: Coloque primero la leche de almendra o el agua en la licuadora. 2. Añadir Ingredientes: Agregue la espinaca, el kiwi, la banana y las semillas de chía. 3. Licuar: Licúe a velocidad media-alta hasta obtener una consistencia suave y homogénea. Si queda demasiado espeso, añada un poco más de líquido. 4. Servir: Sirva inmediatamente. Si lo desea más frío, añada 2-3 cubos de hielo.', 'Excelente fuente de Vitamina C (kiwi), Potasio (banana) y Ácido Fólico (espinacas). La adición de semillas de chía proporciona una dosis importante de fibra soluble y Omega-3, favoreciendo la saciedad y la salud digestiva. Es ideal para un desayuno rápido o una recuperación post-ejercicio.', 'assets/images/recetas/receta_0_20251120_044425_a52d17e4.webp', '2025-11-19 21:44:25', NULL),
(8, 10, 'Pechugas de Pollo Rellenas de Espinacas y Feta', '4 pechugas de pollo deshuesadas y sin piel, 150g de espinacas frescas picadas, 100g de queso feta desmoronado, 1 diente de ajo picado, 2 cucharadas de aceite de oliva, Sal, pimienta, y orégano seco.', 4, '1. Preparar el Relleno: En un bol, mezclar las espinacas, el queso feta, el ajo picado, una cucharada de aceite de oliva, sal, pimienta y orégano. 2. Rellenar el Pollo: Abrir un bolsillo profundo en el lateral de cada pechuga de pollo. Rellenar con la mezcla de espinacas y feta, asegurándose de que el relleno quede dentro. 3. Cocinar: Calentar la cucharada de aceite restante en una sartén grande. Dorar las pechugas por ambos lados. 4. Hornear (Opcional): Terminar de cocinar en el horno precalentado a 180°C durante 15-20 minutos, o hasta que el pollo esté completamente cocido.', 'Excelente fuente de proteína magra (pollo), esencial para la reparación y el mantenimiento muscular. Bajo en grasas saturadas y carbohidratos. El relleno proporciona calcio (feta) y hierro/fibra (espinacas). Perfecto para dietas de control de peso, desarrollo muscular o como una cena ligera.', 'assets/images/recetas/receta_0_20251120_045234_8dde2ff2.avif', '2025-11-19 21:52:34', NULL),
(9, 1, 'Ensalada de vegetales', 'Lechuga, espinaca, huevo', 1, 'Cocer los huevos: Colócalos en una olla con agua fría y llévalos a hervir durante 9-12 minutos, según si los quieres duros. Luego, enfríalos en agua fría, pélalos y córtalos en mitades o cuartos.\r\nLavar y preparar las hojas: Lava bien la lechuga y la espinaca bajo agua corriente, asegurándote de eliminar restos de tierra u arena. Sécalas con una centrifugadora de ensaladas o con toallas de papel, y trocéalas en pedazos manejables.\r\nCortar los tomates: Lava los tomates y córtalos en rodajas, cubos o gajos según prefieras.\r\nArmar la ensalada: En un bol grande, coloca primero las hojas de lechuga y espinaca, luego añade los tomates y el huevo cocido. Mezcla con cuidado para no romper las hojas ni los trozos de huevo.\r\nCondimentar y servir: Añade sal, pimienta y aceite de oliva, o tu aderezo favorito. Opcionalmente, agrega unas gotas de limón, hierbas frescas picadas o un poco de vinagre. Mezcla ligeramente y sirve inmediatamente para disfrutar de la ensalada fresca.', 'Esta ensalada es fresca, nutritiva y versátil,', 'assets/images/recetas/receta_0_20251123_051136_5b202595.jpg', '2025-11-22 22:11:36', NULL),
(18, 15, 'Ensalada', 'Lechuga, espinaca, huevo, tomate', 1, '', 'Esta ensalada es fresca, nutritiva y versátil,', NULL, '2026-02-10 14:35:29', NULL),
(20, 20, 'Ensalada de vegetales', 'Lechuga, espinaca, huevo', 1, '', 'Esta ensalada es fresca, nutritiva y versátil,', NULL, '2026-03-08 23:16:43', NULL),
(21, 7, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(22, 11, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(23, 5, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(24, 20, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(25, 12, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(26, 1, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(27, 19, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(28, 17, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(29, 4, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(30, 16, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(31, 14, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(32, 2, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(33, 13, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(34, 3, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(35, 6, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(36, 18, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(37, 15, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(38, 8, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(39, 10, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL),
(40, 9, 'Ensalada de Quinoa Mediterránea con Pollo al Limón', '1/2 taza de quinoa cocida\r\n\r\n120g de pechuga de pollo a la plancha en cubos\r\n\r\n1/4 de pepino picado\r\n\r\n5 tomates cherry a la mitad\r\n\r\n1 cucharada de aceite de oliva\r\n\r\nJugo de medio limón\r\n\r\nSal, pimienta y perejil al gusto', 1, 'En un tazón grande, mezcla la quinoa cocida con los vegetales picados.\r\n\r\nAgrega el pollo a la plancha (puedes sazonarlo previamente con un poco de ajo).\r\n\r\nPrepara el aderezo mezclando el aceite de oliva, el limón, la sal y la pimienta.\r\n\r\nVierte el aderezo sobre la ensalada, mezcla bien y decora con perejil fresco.', 'Esta receta es alta en proteínas de alta calidad y fibra. Aporta aproximadamente 380 kcal, 35g de proteína, 30g de carbohidratos complejos y grasas saludables.', 'assets/images/recetas/receta_0_20260309_192509_03faf7c3.webp', '2026-03-09 12:25:09', NULL);

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
(8, 1, 22, 'Puede agregarle pollo', 0, '2025-11-27 16:58:55'),
(9, 20, 15, 'mas peperoni', 0, '2026-03-08 23:22:19'),
(10, 20, 30, 'gracias doc', 0, '2026-03-09 13:37:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuarios` int(15) NOT NULL,
  `Nombre_completo` varchar(50) NOT NULL,
  `sexo` enum('M','F') NOT NULL DEFAULT 'M',
  `Correo_electronico` varchar(50) NOT NULL,
  `Usuario` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL,
  `rol` enum('Medico','Paciente','Administrador') NOT NULL DEFAULT 'Paciente',
  `imagen` varchar(500) DEFAULT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuarios`, `Nombre_completo`, `sexo`, `Correo_electronico`, `Usuario`, `Contrasena`, `rol`, `imagen`, `especialidad`, `telefono`) VALUES
(1, 'Damaris Bonilla Garcia', 'M', 'Dambg@gmail.com', 'Damaris Bonilla G', '$2y$10$SPA6cFDoZ7RC/DgE5PJOheZ3rG7h/taCA9uRbG6e7NAU0IDHCwtdW', 'Paciente', NULL, NULL, NULL),
(2, 'Jose Levi Canales', 'M', 'canaleslevi@hotmail.com', 'Levi Canales', '$2y$10$RsWgM5yK7EInM92pAjfnae7wr86PaHUaO8oi5Q.Ek96CMMdh6XQQe', 'Medico', NULL, NULL, NULL),
(3, 'Juan Perez', 'M', 'Jperez30@gmail.com', 'J Perez', '$2y$10$81Yn2vE2K4QflfgSeK2Eb.yh/ThDlGcECAQxT45Tt205D7HENpfK2', 'Medico', NULL, NULL, NULL),
(4, 'Evelenth Garcia', 'M', 'Eygarcia@gmail.com', 'E Garcia', '$2y$10$NCVgVAoAz40berzOj0cUR.yaXRX6tgmtnwRS1dEiHLJIHWpsbkPKu', 'Medico', NULL, NULL, NULL),
(5, 'Jazel Bonilla', 'M', 'gjazelbonilla@gmail.com', 'G Jazel Bonilla', '$2y$10$ubreqJoa9ejIWJyw98/cBO/mAGHDjaI/Vyg2OKv4mZ6qjahrkEb8C', 'Medico', NULL, NULL, NULL),
(6, 'Lizbeth Dominguez', 'M', 'lizd@gmail.com', 'Lizbeth D', '$2y$10$z6sy5o8yGEtwxNoZq9aqLePBdEfVsI5B1B4S38.IvRFlY/.9jtMyO', 'Medico', NULL, NULL, NULL),
(7, 'Arnold Cruz', 'M', 'cruz01@gmail.com', 'Arnold Cruz B', '$2y$10$AiBaVE0Ef.j3vu.gfAHlUeZrWVT4IE8bRjZmaPTuxTI.NlOWN08gm', 'Medico', NULL, NULL, NULL),
(8, 'Luli Garcia', 'M', 'lugarci01@gmail.com', 'luli', '$2y$10$s0TUmmufgCLn/cvH2SLzDesoVOGcyTL/wWhDABB6LUdyu1vjKNv5i', 'Medico', NULL, NULL, NULL),
(9, 'anthony', 'M', 'a@a.mx', 'anthony', '$2y$10$.P3ARwO3S/9ggXL0fNhy/.mTj/1jJQyGtTd04wcO10XIhyLAeZkOm', 'Medico', NULL, NULL, NULL),
(10, 'kaleth', 'M', 'k@a.mx', 'Kaleth', '$2y$10$S4dPMAj3/ZWu7fM1Lu8F9uWgET8oqREJKu2iV9cTdbHwHzMZRdZIq', 'Medico', NULL, NULL, NULL),
(11, 'prueba', 'M', 'prueba@c.com', 'prueba', '$2y$10$0y3d5/ao2gKO98P3ny7Uk.1FjE1q7p/Uf8JMpyPbx4QUYCAdsYoQq', 'Medico', NULL, NULL, NULL),
(12, 'a', 'M', 'a@a.com', 'a', '$2y$10$9960JcPoRO0avYatpjfG5e1lCgJNbfNc.6ouLhjYLf/.gnflAvl/G', 'Paciente', NULL, NULL, NULL),
(13, 'leonel messi', 'M', 'messi@gmail.com', 'campeon', '$2y$10$uQ6XY7hUPPd0m37l28hNi.j3OLzfnaD0tKiJvsVUs15/Jx8KwtYae', 'Medico', NULL, NULL, NULL),
(14, 'ba', 'M', 'ba@c.com', 'chocolate', '$2y$10$JJ3tdsGongFs2j933EKkhOj57q5Bp9MpssVlPteIENY6e2PWQzVPG', 'Medico', NULL, NULL, NULL),
(15, 'admin', 'M', 'admin@correo.com', 'admin', '$2y$10$iVKvzwR/2NcOZw4A.sueee6cDzJC864aR62R6lFHCQaK8pXvtwiUK', 'Administrador', NULL, NULL, NULL),
(17, 'paciente11', 'M', 'paciente11@correo.com', 'paciente11', '$2y$10$X/amdta3MMv/up/wBoqYpO1GoNfvC1ibHhCTvEbBhIXcl3KfdliPG', 'Paciente', NULL, NULL, NULL),
(18, 'paciente12', 'M', 'paciente12@correo.com', 'paciente12', '$2y$10$8BikHqKvCN9Wh3XUC9l5vORmkgFJQN.UPd8Yp/odnlcX38S4EKTNq', 'Paciente', NULL, NULL, NULL),
(19, 'Jose Santos Bonilla', 'M', 'jose@gmail.com', 'Jose Bonilla', '$2y$10$4MVRUIme8RAezcl8bUb71el27Jk07bb.1KCmpcZA.jTaHDKzdMxhK', 'Paciente', NULL, NULL, NULL),
(20, 'Benjamin Garcia', 'M', 'benja@gmail.com', 'Benjamin', '$2y$10$J6OnyCL2R.6hBqxubSZ51Ogquz0pmEH1vLndABDzYmrzunt345l9S', 'Medico', NULL, NULL, NULL),
(21, 'Kari Ruiz', 'M', 'ruiz@gmail.com', 'Kari', '$2y$10$cTGl9zCjl.W..aNc./eS0uJO.oPeuTsEbgUnqRqoRIcHreOzWFr0e', 'Paciente', NULL, NULL, NULL),
(22, 'Genesis Garcia', 'M', 'garcia@gmail.com', 'Genesis', '$2y$10$akWYDyXqdPg/A1OgLs9aZuCIdADKpgULyY7Hb52FHdKbyI1v3fSg2', 'Medico', 'uploads/perfiles/medico_22_1772143911.jpg', '', ''),
(23, 'LEO', 'M', 'leo@gmail.com', 'Leo', '$2y$10$QfhM0ijUfTI9Wto7Qp.6k.RwCkOBIGNtnM6T246aMZ7aL5qUBZiMK', 'Paciente', NULL, NULL, NULL),
(24, 'Maria Cruz', 'M', 'marycruz@gmail.com', 'marycruz@gmail.com', '$2y$10$trZDp8gfWgwGTgxWCtk2DeSX1XsttEDCtrqpPzwAerIT9cD7MJP9G', 'Paciente', NULL, NULL, NULL),
(25, 'Fernando Garcia', 'M', 'Fer@gmail.com', 'Fer1', '$2y$10$Z.GW2ZZe7/1TfhvDV/n7nOdmKpf0TY.nwy0/O58SFOWeNfA0WZn1a', 'Paciente', NULL, NULL, NULL),
(26, 'Denser', 'M', 'denser@nutri.hn', 'denser@nutri.hn', '$2y$10$fOUzfuN/RPFll2f97rRNYezNSwWQ.S8cNQphiPrm0BsZtDDfkBRL2', 'Paciente', NULL, NULL, NULL),
(27, 'David Pacheco', 'M', 'denserpacheco0@gmail.com', 'Deivid96', '$2y$10$9NHpoz2FLssZpPQ65fA.9OKFmqXO89MLBMEZq/Jq0apFd8RlTK2Zu', 'Paciente', NULL, NULL, NULL),
(28, 'Fernanda Hola', 'M', 'fsmendez2002@hotmail.com', 'fsmendez2002@hotmail.com', '$2y$10$6vqVkMx9mTclSe2GABQ.GObhNUV57PrvE4BZtY1yh2zp9VAFr/YKu', 'Paciente', NULL, NULL, NULL),
(29, 'Genesis 2026', 'M', 'jazelbonilla16@gmail.com', 'jazelbonilla16@gmail.com', '$2y$10$3UtUDOCjukYsHGnKxV5rf.Zy9xq1npXc6OD5heHUSvYbOlJqcjfXm', 'Paciente', NULL, NULL, NULL),
(30, 'Anthony paciente', 'M', 'Anthonypaciente@correo.com', 'Anthonypaciente', '$2y$10$J226MYcYTc5xzPKg85B1NO8qunmAoFqI2kraMr1QymSUYCFxlOwb.', 'Paciente', NULL, NULL, NULL),
(31, 'Anthony Kalth', 'M', 'akra5669@gmail.com', 'akra5669@gmail.com', '$2y$10$exWFHFURZkPRJj46qzQmm.u6XBxIylfqfGcSAwiBhEgLn8tmROf4W', 'Paciente', NULL, NULL, NULL),
(32, 'Ana Gabriela Nolasco', 'F', 'anagn@nutri.hn', 'anagn@nutri.hn', '$2y$10$oWM9wt79XCdiwA0xigHk8OL7YzYRZZJR9sBcXW47gmKKoD9TiSUdq', 'Paciente', NULL, NULL, NULL);

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
-- Indices de la tabla `consultas_medicas`
--
ALTER TABLE `consultas_medicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_paciente` (`paciente_id`),
  ADD KEY `idx_medico` (`medico_id`),
  ADD KEY `idx_fecha` (`fecha`);

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
-- Indices de la tabla `examenes`
--
ALTER TABLE `examenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pacientes` (`id_pacientes`);

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
-- Indices de la tabla `inicio_banners`
--
ALTER TABLE `inicio_banners`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `inicio_config`
--
ALTER TABLE `inicio_config`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `inicio_tarjetas`
--
ALTER TABLE `inicio_tarjetas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_pacientes`),
  ADD KEY `id_usuarios` (`id_usuarios`);

--
-- Indices de la tabla `pac_banners`
--
ALTER TABLE `pac_banners`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id_alimento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `alimentos_registro`
--
ALTER TABLE `alimentos_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `consultas_medicas`
--
ALTER TABLE `consultas_medicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `disponibilidades`
--
ALTER TABLE `disponibilidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT de la tabla `ejercicios`
--
ALTER TABLE `ejercicios`
  MODIFY `id_ejercicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `examenes`
--
ALTER TABLE `examenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `expediente`
--
ALTER TABLE `expediente`
  MODIFY `id_expediente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `historial_actualizaciones`
--
ALTER TABLE `historial_actualizaciones`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT de la tabla `inicio_banners`
--
ALTER TABLE `inicio_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `inicio_tarjetas`
--
ALTER TABLE `inicio_tarjetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_pacientes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `pac_banners`
--
ALTER TABLE `pac_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `recetas`
--
ALTER TABLE `recetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `retroalimentacion`
--
ALTER TABLE `retroalimentacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuarios` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

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
