# vpn-saas-reseller

> **GitHub 仓库描述（Description）建议填写：** `B 站分销商站 — Laravel 终端用户前台与分销商门户，对接 A 站控制面。`

独立 Laravel 应用：终端用户选购、订单、已购产品；分销商配置产品与查看数据。**API Key 由 A 站签发，经 A 站 API 校验，不在 B 站伪造存储。** 单独部署一台服务器，Web 根目录指向 `public/`。

本仓库中路径为 **`sites/B`**（与历史 monorepo 路径 **`2.0/php/B`** 对应）；与 A 站 **`sites/A`**（或 **`2.0/php/A`**）版本需配套升级。推送到 **单独 GitHub 仓库** 的步骤见 **`docs/GITHUB_PUSH.md`**；整仓推送见仓库根目录 **`GITHUB_PUSH.md`**（若有）。

## 功能概览

| 模块 | 路径 |
|------|------|
| 用户首页 / 产品 | `/`、`/products` |
| 注册 / 登录 | `/register`、`/login` |
| 订单、已购、个人中心、下载 | `/orders`、`/subscriptions`、`/profile`、`/downloads` |
| 分销商后台（SPA + API） | `/reseller`、`/reseller/login` |
| 分销商 API | `/api/v1/reseller/*`（Bearer = A 站 API Key） |

技术栈：PHP 8.2+、Laravel、SQLite（当前运行）或 MySQL/PostgreSQL、Vite。

---

## 部署要求

- PHP ≥ 8.2，扩展满足 Laravel 与 `composer.json`
- Composer 2.x、Node.js 20+
- **必须先部署 A 站（vpn-control-plane）**，并在 A 站为分销商创建 **API Key**
- B 站 `.env` 中配置：`VPN_A_URL`、`VPN_A_RESELLER_API_KEY`（及可选 `VPN_A_RESELLER_ID`）

---

## 部署步骤（生产）

### 1. 获取代码

```bash
git clone https://github.com/<你的用户名>/vpn-saas-reseller.git
cd vpn-saas-reseller
```

### 2. 环境变量

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`（至少）：

| 变量 | 说明 |
|------|------|
| `APP_URL` | B 站公网 HTTPS 地址，末尾无 `/` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `DB_*` | 默认模板为 **SQLite**（`database/database.sqlite`）；生产可改为 MySQL |
| `VPN_A_URL` | A 站根 URL，与 `https://` 一致，**无末尾斜杠** |
| `VPN_A_RESELLER_API_KEY` | 在 A 站「分销商」中为该 B 站所属分销商生成的 Key |
| `VPN_A_RESELLER_ID` | 分销商 ID（与 A 站一致，用于本地产品归属等） |
| `RESELLER_ADMIN_USERNAME` | 分销商后台登录用户名 |
| `RESELLER_ADMIN_PASSWORD_HASH` | bcrypt 哈希，见下方 |

**生成分销商后台密码哈希：**

```bash
php -r "echo password_hash('你的强密码', PASSWORD_BCRYPT), PHP_EOL;"
```

将输出填入 `RESELLER_ADMIN_PASSWORD_HASH`。

**切勿**提交真实 `.env`。

### 3. 依赖与数据库

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

SQLite 场景确保 `database` 目录可写；然后：

```bash
touch database/database.sqlite   # 若使用默认 sqlite 路径
php artisan migrate --force
```

（按需 `db:seed`，以项目内 Seeder 为准。）

### 4. 权限

```bash
chown -R www-data:www-data storage bootstrap/cache database/database.sqlite
chmod -R ug+rwx storage bootstrap/cache
```

### 5. Web 服务器

文档根指向 `public/`；HTTPS；`APP_URL` 与域名一致。

### 6. 与 A 站联调

1. A 站可访问：`curl -sS "$VPN_A_URL/api/health"`
2. 在 A 站创建分销商并生成 **API Key**，写入 B 站 `VPN_A_RESELLER_API_KEY`
3. B 站分销商后台「API Keys」或「连接 A 站」类配置中，确保 URL 与 Key 一致（若界面有 `.env` 同步工具则按界面保存后重启 PHP）

**独立公网 IP 与区域（与 A 站产品字段对齐）**  
A 站产品可开启 **`requires_dedicated_public_ip`**。此类产品在调用 **`POST /api/v1/reseller/orders` 开通/续费** 时，请求体必须包含有效的 **`region`**（与 A 站 `exit_nodes` / 接入服务器区域一致），否则 A 站会返回 422。B 站选购流程与产品展示应对该标志做提示（例如强制选线路/区域）。同步产品列表时请保留该字段并在前端使用。

---

## 分销商 API（节选）

- `POST /api/v1/reseller/auth` — 使用 A 站 API Key 登录，返回 token
- `GET /api/v1/reseller/me`、`/stats`、`/me/api_keys`
- `GET /api/v1/reseller/a_products`、`/products` 等

详见代码内路由与 `routes/api.php`。

---

## 本地开发

```bash
composer install
cp .env.example .env
php artisan key:generate
# 配置 VPN_A_URL、VPN_A_RESELLER_API_KEY 指向你的 A 站
touch database/database.sqlite
php artisan migrate
npm install && npm run build
php artisan serve
```

---

## 相关仓库

- **A 站控制面：** 仓库名建议 `vpn-control-plane`，与 B 站独立部署、独立版本管理。
