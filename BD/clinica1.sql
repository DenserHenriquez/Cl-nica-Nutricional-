-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
<<<<<<< Updated upstream
-- Generation Time: Oct 27, 2025 at 01:16 AM
=======
-- Generation Time: Nov 06, 2025 at 09:48 PM
>>>>>>> Stashed changes
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clinica1`
--

-- --------------------------------------------------------

--
<<<<<<< Updated upstream
=======
-- Table structure for table `alimentos_registro`
--

CREATE TABLE `alimentos_registro` (
  `id` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo_comida` varchar(20) NOT NULL,
  `descripcion` text NOT NULL,
  `hora` time NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alimentos_registro`
--

INSERT INTO `alimentos_registro` (`id`, `paciente_id`, `fecha`, `tipo_comida`, `descripcion`, `hora`, `foto_path`, `created_at`) VALUES
(1, 6, '2025-10-26', 'desayuno', 'Pan integral con huevo picado , jamon y ensalada', '07:00:00', 'assets/images/alimentos/paciente_6_20251028_025321_b05ab5cf.jpg', '2025-10-27 19:53:21'),
(2, 3, '2025-10-17', 'desayuno', 'Pan Integral con aguacate y huevo tibio', '08:10:00', 'assets/images/alimentos/paciente_3_20251028_031206_38c2c6c7.jpg', '2025-10-27 20:12:06'),
(3, 3, '2025-10-17', 'almuerzo', 'Pollo a la plancha con ensalada', '12:00:00', 'assets/images/alimentos/paciente_3_20251028_031902_651d8125.png', '2025-10-27 20:19:02'),
(5, 6, '2025-10-12', 'almuerzo', 'Pollo cosido', '12:00:00', 'assets/images/alimentos/paciente_6_20251028_041537_de8301ae.png', '2025-10-27 21:15:37'),
(7, 4, '2025-10-27', 'desayuno', 'Bowl de frutas', '07:30:00', 'assets/images/alimentos/paciente_420251028_064942fd3904f3.png', '2025-10-27 23:49:42'),
(8, 4, '2025-10-28', 'almuerzo', 'Arroz, Pollo y ensalada de aguacate y tomate', '12:00:00', 'assets/images/alimentos/paciente_420251028_065200d392ddce.jpg', '2025-10-27 23:52:00'),
(9, 1, '2025-10-29', 'almuerzo', 'Arroz, pollo, ensalada de aguacate y tomate', '12:20:00', 'assets/images/alimentos/paciente_120251029_224346b63ec590.jpg', '2025-10-29 15:43:46');

-- --------------------------------------------------------

--
-- Table structure for table `citas`
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

-- --------------------------------------------------------

--
-- Table structure for table `disponibilidades`
--

CREATE TABLE `disponibilidades` (
  `id` int(11) NOT NULL,
  `medico_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('libre','bloqueado') NOT NULL DEFAULT 'libre'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ejercicios`
--

CREATE TABLE `ejercicios` (
  `id_ejercicio` int(11) NOT NULL,
  `id_pacientes` int(11) NOT NULL,
  `tipo_ejercicio` varchar(100) NOT NULL,
  `tiempo` int(11) NOT NULL COMMENT 'Duración en minutos',
  `hora` time NOT NULL DEFAULT '00:00:00',
  `fecha` date NOT NULL,
  `imagen_evidencia` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ejercicios`
--

INSERT INTO `ejercicios` (`id_ejercicio`, `id_pacientes`, `tipo_ejercicio`, `tiempo`, `hora`, `fecha`, `imagen_evidencia`, `notas`, `fecha_registro`) VALUES
(1, 1, 'Gimnasio', 30, '00:00:00', '2025-10-27', NULL, 'Realize remo con mancuernas', '2025-10-27 19:19:53'),
(2, 1, 'Caminata', 30, '00:00:00', '2025-10-28', 'uploads/ejercicios/69003be939acb_Caminata.jpg', '', '2025-10-28 03:43:37'),
(3, 1, 'Caminata', 30, '00:00:00', '2025-10-28', 'uploads/ejercicios/69003c817865a_Caminata.jpg', '', '2025-10-28 03:46:09'),
(4, 4, 'Correr', 30, '00:00:00', '2025-10-27', 'uploads/ejercicios/690055ed4d1a3_Caminata.jpg', '', '2025-10-28 05:34:37');

-- --------------------------------------------------------

--
-- Table structure for table `expediente`
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

-- --------------------------------------------------------

--
-- Table structure for table `historial_actualizaciones`
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
-- Dumping data for table `historial_actualizaciones`
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
(14, 1, 'telefono', '99553211', '33456012', 1, '2025-10-30 22:29:27');

-- --------------------------------------------------------

--
>>>>>>> Stashed changes
-- Table structure for table `pacientes`
--

CREATE TABLE `pacientes` (
  `id_pacientes` int(11) NOT NULL,
  `id_usuarios` int(11) NOT NULL,
  `nombre_completo` varchar(100) DEFAULT NULL,
  `DNI` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pacientes`
--

<<<<<<< Updated upstream
INSERT INTO `pacientes` (`id_pacientes`, `id_usuarios`, `nombre_completo`, `DNI`, `fecha_nacimiento`, `edad`, `telefono`) VALUES
(1, 1, 'Damaris Bonilla G', '0823200610125', '2006-08-01', 19, '99553364');
=======
INSERT INTO `pacientes` (`id_pacientes`, `id_usuarios`, `nombre_completo`, `DNI`, `fecha_nacimiento`, `edad`, `telefono`, `estado`) VALUES
(1, 1, 'Damaris  Bonilla Garcia', '0823200610125', '2005-08-01', 20, '33456012', 'Activo'),
(2, 2, 'Jose Levi Canales', '1705201000216', '2010-10-08', 15, '32653641', 'Activo'),
(3, 3, 'Juan David Perez ', '0801200015300', '2025-04-08', 25, '33452018', 'Inactivo'),
(4, 4, 'Evelenth Garcia', '0823200013256', '2000-07-06', 25, '33654892', 'Inactivo');
>>>>>>> Stashed changes

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuarios` int(15) NOT NULL,
  `Nombre_completo` varchar(50) NOT NULL,
  `Correo_electronico` varchar(50) NOT NULL,
  `Usuario` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuarios`, `Nombre_completo`, `Correo_electronico`, `Usuario`, `Contrasena`) VALUES
<<<<<<< Updated upstream
(1, 'Damaris Bonilla G', 'Dambg@gmail.com', 'Damaris Bonilla G', 'dam1234n');
=======
(1, 'Damaris Bonilla Garcia', 'Dambg@gmail.com', 'Damaris Bonilla G', '$2y$10$SPA6cFDoZ7RC/DgE5PJOheZ3rG7h/taCA9uRbG6e7NAU0IDHCwtdW'),
(2, 'Jose Levi Canales', 'canaleslevi@hotmail.com', 'Levi Canales', '$2y$10$RsWgM5yK7EInM92pAjfnae7wr86PaHUaO8oi5Q.Ek96CMMdh6XQQe'),
(3, 'Juan Perez', 'Jperez30@gmail.com', 'J Perez', '$2y$10$81Yn2vE2K4QflfgSeK2Eb.yh/ThDlGcECAQxT45Tt205D7HENpfK2'),
(4, 'Evelenth Garcia', 'Eygarcia@gmail.com', 'E Garcia', '$2y$10$NCVgVAoAz40berzOj0cUR.yaXRX6tgmtnwRS1dEiHLJIHWpsbkPKu'),
(5, 'Jazel Bonilla', 'gjazelbonilla@gmail.com', 'G Jazel Bonilla', '$2y$10$ubreqJoa9ejIWJyw98/cBO/mAGHDjaI/Vyg2OKv4mZ6qjahrkEb8C'),
(6, 'Lizbeth Dominguez', 'lizd@gmail.com', 'Lizbeth D', '$2y$10$z6sy5o8yGEtwxNoZq9aqLePBdEfVsI5B1B4S38.IvRFlY/.9jtMyO'),
(7, 'Arnold Cruz', 'cruz01@gmail.com', 'Arnold Cruz B', '$2y$10$AiBaVE0Ef.j3vu.gfAHlUeZrWVT4IE8bRjZmaPTuxTI.NlOWN08gm'),
(8, 'Luli Garcia', 'lugarci01@gmail.com', 'luli', '$2y$10$s0TUmmufgCLn/cvH2SLzDesoVOGcyTL/wWhDABB6LUdyu1vjKNv5i');
>>>>>>> Stashed changes

--
-- Indexes for dumped tables
--

--
<<<<<<< Updated upstream
=======
-- Indexes for table `alimentos_registro`
--
ALTER TABLE `alimentos_registro`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_paciente_fecha` (`paciente_id`,`fecha`);

--
-- Indexes for table `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cita` (`medico_id`,`fecha`,`hora`);

--
-- Indexes for table `disponibilidades`
--
ALTER TABLE `disponibilidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`medico_id`,`fecha`,`hora`);

--
-- Indexes for table `ejercicios`
--
ALTER TABLE `ejercicios`
  ADD PRIMARY KEY (`id_ejercicio`),
  ADD KEY `id_pacientes` (`id_pacientes`);

--
-- Indexes for table `expediente`
--
ALTER TABLE `expediente`
  ADD PRIMARY KEY (`id_expediente`),
  ADD KEY `id_pacientes` (`id_pacientes`);

--
-- Indexes for table `historial_actualizaciones`
--
ALTER TABLE `historial_actualizaciones`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_usuarios` (`id_usuarios`),
  ADD KEY `actualizado_por` (`actualizado_por`);

--
>>>>>>> Stashed changes
-- Indexes for table `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_pacientes`),
  ADD KEY `id_usuarios` (`id_usuarios`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuarios`);

--
-- AUTO_INCREMENT for dumped tables
--

--
<<<<<<< Updated upstream
=======
-- AUTO_INCREMENT for table `alimentos_registro`
--
ALTER TABLE `alimentos_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `citas`
--
ALTER TABLE `citas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disponibilidades`
--
ALTER TABLE `disponibilidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ejercicios`
--
ALTER TABLE `ejercicios`
  MODIFY `id_ejercicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expediente`
--
ALTER TABLE `expediente`
  MODIFY `id_expediente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `historial_actualizaciones`
--
ALTER TABLE `historial_actualizaciones`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
>>>>>>> Stashed changes
-- AUTO_INCREMENT for table `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_pacientes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
<<<<<<< Updated upstream
  MODIFY `id_usuarios` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
=======
  MODIFY `id_usuarios` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
>>>>>>> Stashed changes

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pacientes`
--
ALTER TABLE `pacientes`
  ADD CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`id_usuarios`) REFERENCES `usuarios` (`id_usuarios`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
