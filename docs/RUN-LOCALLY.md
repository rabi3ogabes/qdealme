# Run QDealMe on your computer

About 15 minutes the first time. You need four free programs, then five commands.

## 1. Install the tools (once)

| | Windows | Mac |
|---|---|---|
| PHP, Composer and Node.js | [Laravel Herd](https://herd.laravel.com) | [Laravel Herd](https://herd.laravel.com) |
| PostgreSQL database | [PostgreSQL installer](https://www.postgresql.org/download/windows/) (keep the password you choose) | [Postgres.app](https://postgresapp.com) |
| Git | [Git for Windows](https://git-scm.com/download/win) | Already installed (or run `xcode-select --install`) |
| PDF support (optional, only to upload PDF flyers) | `winget install oschwartz10612.Poppler` | `brew install poppler` |

After installing Herd, open it once so it sets up `php`, `composer` and `npm` for your terminal.

## 2. Create the database

- **Windows:** open **pgAdmin** (installed with PostgreSQL), right-click **Databases → Create → Database**, and name it `qdealme`.
- **Mac:** open Postgres.app, click **Initialize**, then in Terminal run `createdb qdealme`.

## 3. Get the code and settings

Open a terminal (**PowerShell** on Windows, **Terminal** on Mac):

```bash
git clone https://github.com/rabi3ogabes/qdealme.git
cd qdealme
git checkout claude/qdealme-laravel-app-hviz4s
cp .env.example .env
```

Open `.env` in any text editor and change these lines:

```dotenv
DB_USERNAME=postgres
DB_PASSWORD=the-password-you-chose        # Mac with Postgres.app: leave empty
ADMIN_PASSWORD=choose-a-strong-password
ADMIN_REQUIRE_MFA=false                   # only on your own computer
```

## 4. Install and fill the database

```bash
composer install
npm install
npm run build
composer setup
```

`composer setup` creates the tables, the cities and categories, your admin account and ten fictional demo stores with flyers. The demo flyers take about two minutes.

## 5. Start it

```bash
composer dev
```

Keep that window open, then visit:

- **Website:** http://localhost:8000. It opens in Arabic; the **EN** button switches to English.
- **Admin:** http://localhost:8000/admin. Log in with `admin@qdealme.com` and your `ADMIN_PASSWORD`.

To stop, press `Ctrl + C`. Next time, only run `composer dev` from the `qdealme` folder.

## If something goes wrong

| Message | Fix |
|---|---|
| `could not find driver` | In Herd, open **PHP → Extensions** and turn on `pdo_pgsql`, then restart the terminal. |
| `password authentication failed` | `DB_USERNAME` / `DB_PASSWORD` in `.env` don't match your PostgreSQL login. |
| `database "qdealme" does not exist` | Repeat step 2. |
| `Vite manifest not found` | Run `npm run build`. |
| Flyers don't publish or expire on time | Run `php artisan schedule:work` in a second terminal window. |
