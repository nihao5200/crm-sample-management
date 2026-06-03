-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-06-03 10:50:57
-- 服务器版本： 5.7.26
-- PHP 版本： 7.2.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `crm`
--

-- --------------------------------------------------------

--
-- 表的结构 `admin`
--

CREATE TABLE `admin` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码(加密存储)',
  `role` tinyint(1) NOT NULL DEFAULT '1' COMMENT '角色：1管理员 2普通用户',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录IP',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

--
-- 转存表中的数据 `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `role`, `status`, `last_login_time`, `last_login_ip`, `create_time`, `update_time`) VALUES
(1, 'admin', '$2y$10$cRQoyhUri2hLIMXGmp6RQuX8wZkI9aq2vkmKLAf2jy4SQ5rDn/zDu', 1, 1, '2026-06-03 10:45:48', '127.0.0.1', '2026-06-02 09:54:07', '2026-06-03 10:46:02');

-- --------------------------------------------------------

--
-- 表的结构 `customer`
--

CREATE TABLE `customer` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '客户名称',
  `contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '电话',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地址',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户信息表';

--
-- 转存表中的数据 `customer`
--

INSERT INTO `customer` (`id`, `customer_name`, `contact`, `phone`, `email`, `address`, `remark`, `create_time`, `update_time`) VALUES
(6, '惠州市电子有限公司', '', '', '', '', '', '2026-06-02 15:56:33', '2026-06-03 09:55:59'),
(7, '浙江半导体有限公司', '', '', '', '', '', '2026-06-02 15:56:33', '2026-06-03 09:56:08'),
(8, '海宁电子有限公司', '', '', '', '', '', '2026-06-02 15:56:33', '2026-06-03 09:56:16'),
(9, '深圳市电子科技有限公司', '', '', '', '', '', '2026-06-02 15:56:33', '2026-06-03 09:56:25'),
(10, '丽水市制造有限公司', '', '', '', '', '', '2026-06-02 15:56:33', '2026-06-03 09:56:36'),
(12, '东莞市X电子科技有限公司', '', '', '', '', '', '2026-06-02 15:56:33', '2026-06-03 09:57:05'),
(18, '东莞市科技有限公司', '', '', '', '', '', '2026-06-02 15:56:34', '2026-06-03 09:55:34'),
(19, '北京科技有限公司', '', '', '', '', '', '2026-06-02 15:56:34', '2026-06-03 09:55:42'),
(20, '深圳科技有限公司', '', '', '', '', '', '2026-06-02 15:56:34', '2026-06-03 09:55:51');

-- --------------------------------------------------------

--
-- 表的结构 `order_detail`
--

CREATE TABLE `order_detail` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `order_id` int(11) UNSIGNED NOT NULL COMMENT '关联订单主表ID',
  `product_model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '产品型号',
  `quantity` int(11) NOT NULL DEFAULT '0' COMMENT '数量',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '个' COMMENT '单位',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '金额(数量*单价)',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '颜色',
  `ratio` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '比例',
  `tax_included` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否含税：0否 1是',
  `express` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '快递公司',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单明细表';

--
-- 转存表中的数据 `order_detail`
--

INSERT INTO `order_detail` (`id`, `order_id`, `product_model`, `quantity`, `unit`, `unit_price`, `amount`, `color`, `ratio`, `tax_included`, `express`, `remark`, `create_time`) VALUES
(1, 8, '铜材', 1, 'kg', '180.00', '180.00', '', '', 0, '京东', '', '2026-06-03 10:00:14');

-- --------------------------------------------------------

--
-- 表的结构 `order_main`
--

CREATE TABLE `order_main` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单号(唯一)',
  `customer_id` int(11) UNSIGNED NOT NULL COMMENT '关联客户ID',
  `order_date` date NOT NULL COMMENT '下单日期',
  `ship_date` date NOT NULL COMMENT '出货日期',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单状态：1待生产 2生产中 3已发货 4已完成 5已取消',
  `order_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单类型：1普通订单 2现金订单',
  `payment_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '收款状态：1未付款 2部分付款 3已结清',
  `origin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产地：惠州、东莞、湖南',
  `order_total_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '订单总金额（明细合计）',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '已收金额（关联收款合计）',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单主表';

--
-- 转存表中的数据 `order_main`
--

INSERT INTO `order_main` (`id`, `order_no`, `customer_id`, `order_date`, `ship_date`, `status`, `order_type`, `payment_status`, `origin`, `order_total_amount`, `paid_amount`, `remark`, `create_time`, `update_time`) VALUES
(8, 'ORD202606033167', 12, '2026-06-03', '2026-06-03', 1, 1, 2, '', '180.00', '100.00', '', '2026-06-03 10:00:14', '2026-06-03 10:00:42');

-- --------------------------------------------------------

--
-- 表的结构 `order_payment_link`
--

CREATE TABLE `order_payment_link` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `order_id` int(11) UNSIGNED NOT NULL COMMENT '关联订单ID',
  `payment_id` int(11) UNSIGNED NOT NULL COMMENT '关联收款记录ID',
  `link_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '关联金额',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单-收款关联表';

--
-- 转存表中的数据 `order_payment_link`
--

INSERT INTO `order_payment_link` (`id`, `order_id`, `payment_id`, `link_amount`, `create_time`) VALUES
(6, 8, 6, '100.00', '2026-06-03 10:00:42');

-- --------------------------------------------------------

--
-- 表的结构 `order_recipient`
--

CREATE TABLE `order_recipient` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `order_id` int(11) UNSIGNED NOT NULL COMMENT '关联订单主表ID',
  `recipient_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '收件人姓名',
  `recipient_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收件人电话',
  `recipient_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '收件地址',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认：0否 1是',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单收件人表';

--
-- 转存表中的数据 `order_recipient`
--

INSERT INTO `order_recipient` (`id`, `order_id`, `recipient_name`, `recipient_phone`, `recipient_address`, `is_default`, `sort_order`, `create_time`) VALUES
(1, 8, '测试', '13813881388', '湖南城城城城城城', 1, 0, '2026-06-03 10:00:14');

-- --------------------------------------------------------

--
-- 表的结构 `payment_record`
--

CREATE TABLE `payment_record` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `customer_id` int(11) UNSIGNED NOT NULL COMMENT '关联客户ID',
  `payment_date` date NOT NULL COMMENT '收款日期',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '收款金额',
  `payment_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '收款方式：1现金 2银行转账 3微信 4支付宝',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `operator` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `is_cash_order` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否现金订单：0否 1是',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收款记录表';

--
-- 转存表中的数据 `payment_record`
--

INSERT INTO `payment_record` (`id`, `customer_id`, `payment_date`, `amount`, `payment_type`, `remark`, `operator`, `is_cash_order`, `create_time`, `update_time`) VALUES
(6, 12, '2026-06-03', '100.00', 1, '预付款', 'admin', 0, '2026-06-03 10:00:42', '2026-06-03 10:00:42');

-- --------------------------------------------------------

--
-- 表的结构 `sample_record`
--

CREATE TABLE `sample_record` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '主键ID',
  `sample_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '样品号(唯一)',
  `customer_id` int(11) UNSIGNED NOT NULL COMMENT '关联客户ID',
  `product_model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '产品型号',
  `quantity` int(11) NOT NULL DEFAULT '1' COMMENT '数量',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '个' COMMENT '单位',
  `unit_price` decimal(10,2) DEFAULT NULL COMMENT '单价',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '颜色',
  `ratio` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '比例',
  `tax_included` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否含税：0不含税 1含税',
  `send_date` date NOT NULL COMMENT '送样日期',
  `sample_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '样品状态：1待确认 2已确认 3已退回 4已量产',
  `follow_up` text COLLATE utf8mb4_unicode_ci COMMENT '跟进记录',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='样品记录表';

--
-- 转存表中的数据 `sample_record`
--

INSERT INTO `sample_record` (`id`, `sample_no`, `customer_id`, `product_model`, `quantity`, `unit`, `unit_price`, `color`, `ratio`, `tax_included`, `send_date`, `sample_status`, `follow_up`, `remark`, `create_time`, `update_time`) VALUES
(1, 'SMP202606035348', 18, '色板', 1, 'pc', '50.00', '红', '', 1, '2026-06-03', 1, '', '', '2026-06-03 10:46:53', '2026-06-03 10:46:53');

--
-- 转储表的索引
--

--
-- 表的索引 `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_name` (`customer_name`),
  ADD KEY `idx_contact` (`contact`),
  ADD KEY `idx_phone` (`phone`);

--
-- 表的索引 `order_detail`
--
ALTER TABLE `order_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_model` (`product_model`);

--
-- 表的索引 `order_main`
--
ALTER TABLE `order_main`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_order_no` (`order_no`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_order_date` (`order_date`),
  ADD KEY `idx_ship_date` (`ship_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_type` (`order_type`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- 表的索引 `order_payment_link`
--
ALTER TABLE `order_payment_link`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_payment_id` (`payment_id`);

--
-- 表的索引 `order_recipient`
--
ALTER TABLE `order_recipient`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- 表的索引 `payment_record`
--
ALTER TABLE `payment_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_payment_type` (`payment_type`),
  ADD KEY `idx_is_cash_order` (`is_cash_order`);

--
-- 表的索引 `sample_record`
--
ALTER TABLE `sample_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sample_no` (`sample_no`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_product_model` (`product_model`),
  ADD KEY `idx_sample_status` (`sample_status`),
  ADD KEY `idx_send_date` (`send_date`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=25;

--
-- 使用表AUTO_INCREMENT `order_detail`
--
ALTER TABLE `order_detail`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `order_main`
--
ALTER TABLE `order_main`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=9;

--
-- 使用表AUTO_INCREMENT `order_payment_link`
--
ALTER TABLE `order_payment_link`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `order_recipient`
--
ALTER TABLE `order_recipient`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `payment_record`
--
ALTER TABLE `payment_record`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `sample_record`
--
ALTER TABLE `sample_record`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID', AUTO_INCREMENT=2;

--
-- 限制导出的表
--

--
-- 限制表 `order_detail`
--
ALTER TABLE `order_detail`
  ADD CONSTRAINT `fk_detail_order` FOREIGN KEY (`order_id`) REFERENCES `order_main` (`id`) ON DELETE CASCADE;

--
-- 限制表 `order_main`
--
ALTER TABLE `order_main`
  ADD CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE;

--
-- 限制表 `order_payment_link`
--
ALTER TABLE `order_payment_link`
  ADD CONSTRAINT `fk_link_order` FOREIGN KEY (`order_id`) REFERENCES `order_main` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_link_payment` FOREIGN KEY (`payment_id`) REFERENCES `payment_record` (`id`) ON DELETE CASCADE;

--
-- 限制表 `order_recipient`
--
ALTER TABLE `order_recipient`
  ADD CONSTRAINT `fk_recipient_order` FOREIGN KEY (`order_id`) REFERENCES `order_main` (`id`) ON DELETE CASCADE;

--
-- 限制表 `payment_record`
--
ALTER TABLE `payment_record`
  ADD CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE;

--
-- 限制表 `sample_record`
--
ALTER TABLE `sample_record`
  ADD CONSTRAINT `fk_sample_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
