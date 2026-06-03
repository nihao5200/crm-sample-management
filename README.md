# 客户订单样品管理系统 (CRM)

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white" alt="Bootstrap">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
</p>

一套功能完善的 **客户订单样品管理系统**，专为中小企业设计，支持客户管理、订单跟踪、样品记录、收款管理、数据报表等核心业务流程。

## ✨ 核心功能

### 📋 客户管理
- 客户信息录入、编辑、删除
- **批量导入**：支持 CSV 批量导入客户（智能表头识别、自动去重）
- 客户搜索：支持按名称、联系人、电话模糊查询
- **未下单提醒**：自动统计 30/60/90 天未下单客户
- 关联显示客户的订单数、样品数、欠款金额

### 📦 订单管理
- 订单录入（支持多条明细、多个收件人）
- **订单状态**：待生产、生产中、已发货、已完成、已取消
- **产地管理**：支持惠州、东莞、湖南等产地标记
- **收款管理**：支持现金、银行转账、微信、支付宝等多种收款方式
- **订单对账**：实时显示订单总额、已收金额、未收金额
- **出货预警**：自动提醒即将到期的订单
- **批量导出**：支持 Excel/CSV 格式导出

### 🧪 样品管理
- 样品登记、跟踪、管理
- **留言板模式**：跟进记录支持追加、修改、删除
- 样品状态：待确认、已确认、已退回、已量产
- 关联客户历史订单

### 💰 收款管理
- 收款记录登记
- 支持关联多个订单
- 自动计算客户欠款
- 收款统计报表

### 📊 数据报表
- 订单统计：按状态、日期、客户统计
- 收款统计：按方式、日期统计
- **数据备份**：一键导出数据库 SQL 文件

### 🔐 系统管理
- 管理员/普通用户权限控制
- 密码修改
- 系统信息查看

## 🚀 快速开始

### 环境要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx Web服务器
- PDO MySQL 扩展

### 安装步骤

#### 1. 克隆项目

```bash
git clone https://github.com/yourusername/crm-system.git
cd crm-system
```

#### 2. 创建数据库

```sql
CREATE DATABASE crm_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3. 导入数据库

```bash
mysql -u root -p crm_system < database.sql
```

#### 4. 配置数据库

编辑 `config/database.php`：

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_system');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

#### 5. 访问系统

```
http://your-domain.com/
```

**默认账号**：
- 用户名：`admin`
- 密码：`admin123`

> ⚠️ 建议登录后立即修改默认密码

## 📁 项目结构

```
crm-system/
├── ajax/                   # AJAX 接口
├── assets/                 # 静态资源
│   ├── css/
│   │   └── style.css      # 自定义样式
│   └── js/
│       └── main.js        # 主JavaScript
├── config/                 # 配置文件
│   ├── database.php       # 数据库配置
│   └── functions.php      # 公共函数
├── includes/              # 模板文件
│   ├── header.php
│   └── footer.php
├── backups/               # 备份文件目录
├── database.sql           # 数据库结构
├── index.php             # 首页仪表盘
├── login.php             # 登录
├── customer.php          # 客户管理
├── customer_import.php   # 客户批量导入
├── customer_no_order.php # 未下单客户
├── order.php             # 订单管理
├── order_form.php        # 订单表单
├── order_pending.php     # 待出货订单
├── sample.php            # 样品管理
├── sample_form.php       # 样品表单
├── payment.php           # 收款管理
├── payment_report.php    # 收款报表
├── setting.php           # 系统设置
├── backup.php            # 数据备份
└── README.md
```

## 🎯 功能亮点

### 智能客户导入
- 支持多种 CSV 编码（UTF-8/GBK/GB2312 自动识别）
- 智能表头匹配（支持"客户名称"/"公司名称"/"客户"等变体）
- 自动去重（已存在客户自动跳过）

### 订单管理增强
- **客户搜索**：集成搜索框，快速定位客户
- **产地标记**：支持多产地分类管理
- **实时计算**：金额自动计算，含税/不含税标记
- **多收件人**：支持一个订单多个收货地址

### 数据安全
- SQL 注入防护（PDO 预处理）
- XSS 攻击防护（输出转义）
- 密码加密存储（bcrypt）
- 权限分级控制

## 📸 界面预览

| 首页仪表盘 | 订单管理 | 客户导入 |
|-----------|---------|---------|
| 统计卡片 | 订单列表 | CSV批量导入 |
| 出货预警 | 订单明细 | 智能去重 |

## 🔧 常见问题

### Q: 登录提示"数据库连接失败"
A: 检查 `config/database.php` 中的数据库配置是否正确，确保 MySQL 服务已启动。

### Q: 客户导入乱码
A: 系统已支持自动编码检测，如仍有问题，请将 CSV 文件保存为 UTF-8 编码格式。

### Q: 如何修改订单状态选项？
A: 编辑 `order_form.php` 和 `order.php` 中的状态选项数组。

### Q: 如何添加新的产地选项？
A: 编辑 `order_form.php` 中的产地 select 选项，并在数据库中添加相应字段。

## 📝 更新日志

### v2.0.0 (2024-06)
- ✨ 新增客户批量导入功能（支持 CSV、自动去重）
- ✨ 新增未下单客户提醒（30/60/90天）
- ✨ 新增产地管理（惠州、东莞、湖南）
- ✨ 新增客户搜索功能（集成搜索框）
- ✨ 新增数据备份功能
- ✨ 新增收款管理和对账功能
- ✨ 样品跟进改为留言板模式
- 🎨 优化首页仪表盘 UI
- 🎨 优化订单明细排版
- 🐛 修复多处已知问题

### v1.0.0 (2024-05)
- 🎉 初始版本发布
- 客户管理、订单管理、样品管理三大核心模块

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本项目
2. 创建你的特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 打开 Pull Request

## 📄 开源协议

本项目基于 [MIT](LICENSE) 协议开源。

## 👨‍💻 开发者

- 作者：[Your Name]
- 邮箱：[your.email@example.com]

---

<p align="center">
  <b>如果这个项目对你有帮助，请给个 ⭐ Star 支持一下！</b>
</p>
