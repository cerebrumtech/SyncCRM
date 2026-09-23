# Running SyncCRM on your own machine

From nothing to the app open in a browser, with Visual Studio Code as the editor.

The app is deliberately plain: **PHP + MySQL and nothing else.** No Composer, no npm, no
build step, no framework. If PHP runs and MySQL runs, the app runs. Node is needed only if
you want to run the browser tests.

Times below are for a first run on Windows; macOS and Linux are faster because the
prerequisites are usually already there.

---

## 1. Install the prerequisites (~20 min, once)

| | Windows | macOS | Ubuntu/Debian |
|---|---|---|---|
| **PHP 8.1+** (7.4 also works) | [windows.php.net/download](https://windows.php.net/download) — take the **Thread Safe** zip, unzip to `C:\php` | `brew install php` | `sudo apt install php-cli php-mysql php-mbstring` |
| **MySQL 8** | [MySQL Installer](https://dev.mysql.com/downloads/installer/) — "Server only" is enough | `brew install mysql && brew services start mysql` | `sudo apt install mysql-server` |
| **Git** | [git-scm.com](https://git-scm.com/download/win) | `brew install git` | `sudo apt install git` |
| **VS Code** | [code.visualstudio.com](https://code.visualstudio.com/) | same | same |

### Windows only — two extra steps

**a. Put PHP on your PATH.** Start menu → "Edit the system environment variables" →
Environment Variables → under *User variables* select `Path` → Edit → New → `C:\php` → OK.
**Close and reopen every terminal** afterwards.

**b. Turn on the extensions.** In `C:\php`, copy `php.ini-development` to `php.ini`, open it
in VS Code and remove the leading `;` from these four lines:

```ini
extension_dir = "ext"
extension=pdo_mysql
extension=mbstring
extension=fileinfo
```

Check it worked:

```powershell
php -v
php -m | findstr /i "pdo_mysql mbstring fileinfo"
```

You should see a version number and all three extension names. If `php` is "not recognized",
the PATH step did not take — reopen the terminal.

*(On macOS/Linux these extensions are on by default; `php -m | grep -E 'pdo_mysql|mbstring|fileinfo'` confirms it.)*

---

## 2. Get the code

```bash
git clone https://github.com/cerebrumtech/synccrm.git
cd synccrm
code .
```

That last line opens the project in VS Code. From here on, use VS Code's built-in terminal:
**Terminal → New Terminal**, or **Ctrl+`**.

VS Code will offer to install the extensions this project recommends (PHP IntelliSense, a
MySQL client, EditorConfig) — say yes. They are only conveniences; nothing depends on them.

---

## 3. Create the database

In the VS Code terminal:

```bash
mysql -u root -p
```

Then, at the `mysql>` prompt:

```sql
CREATE DATABASE synccrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'synccrm'@'localhost' IDENTIFIED BY 'synccrm';
GRANT ALL PRIVILEGES ON synccrm.* TO 'synccrm'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

`utf8mb4` is not optional — some society names are in Devanagari and any other character set
mangles them.

---

## 4. Configure the app

```bash
# Windows PowerShell
copy .env.example .env

# macOS / Linux
cp .env.example .env
```

Open `.env` in VS Code and make it read:

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://127.0.0.1:8080/'
app.uploadDir = ''
app.installKey = ''
app.timezone = 'Asia/Kolkata'

database.default.hostname = localhost
database.default.database = synccrm
database.default.username = synccrm
database.default.password = synccrm
database.default.port = 3306
```

Two notes:

- `CI_ENVIRONMENT = development` shows full errors on screen instead of "Something went
  wrong". Use it locally, never on the server.
- Leaving `app.uploadDir` blank stores uploads under `writable/uploads`, which is fine
  locally. On the server it must point outside the web root.

`.env` is in `.gitignore` and must never be committed.

---

## 5. Create the tables and demo data

```bash
php schema/install.php --seed
```

You should see the tables being created and four sign-ins printed at the end. Run it again
any time — it is idempotent, and it refuses to re-seed over an existing workspace.

---

## 6. Start it

```bash
bash dev/serve.sh
```

On Windows, if `bash` is missing, run the same thing directly:

```powershell
php -S 127.0.0.1:8080 -t public dev/router.php
```

Open **http://127.0.0.1:8080** and sign in:

| Email | Password | Role |
|---|---|---|
| `owner@syncworkstech.com` | `password123` | Owner |
| `admin@syncworkstech.com` | `password123` | Admin |
| `rahul@syncworkstech.com` | `password123` | Member |
| `priya@syncworkstech.com` | `password123` | Member |

**Do not start the server with a bare `php -S ... -t public`.** Without `dev/router.php`, a
URL like `/contacts/12` returns 404 locally although it works in production — the router
reproduces the `try_files` rule nginx applies on the server. `dev/README.md` explains it.

---

## 7. What you are looking at

```
public/index.php      the single entry point; everything routes through here
app/Config/Routes.php every URL in the app, in one file -- start here
app/Controllers/      one per module (Contacts, Companies, Deals, Activities, ...)
app/Models/           one per table
app/Views/            plain PHP templates
app/Libraries/        cross-cutting pieces (Auth, Records, Import, Dedupe)
app/Helpers/          small functions available everywhere
kernel/               the ~1,500-line framework: Router, Model, Database, View, Schema, Env
schema/schema.sql     the whole database in one file
schema/install.php    creates/updates tables; --seed adds demo data
public/assets/        app.css and app.js, served as-is; no build step
deploy/               production scripts and the Cloudways runbook
tests/browser/        Playwright suites (the only part that needs Node)
tools/compat/         PHP 7.4 compatibility checker
dev/                  local dev server and its nginx-shaped router
```

**Adding a screen** is four edits: a route in `app/Config/Routes.php`, a method on a
controller, a view, and a model if it touches a new table. `README.md` has the request-flow
diagram.

---

## 8. Making a change

```bash
git checkout -b my-change
# ...edit...
git add -A
git commit -m "What changed and why"
git push -u origin my-change
```

Then open a pull request on GitHub. `docs/PROCESS.md` describes the agreed process —
Jira ticket, approval, changelog entry, and the rule that schema changes go through a
staging check first.

Before you push anything that will reach the server, run the PHP 7.4 check:

```bash
cd tools/compat && composer install       # once
php check74.php ../../app ../../kernel ../../public ../../schema
```

The production server is pinned to PHP 7.4.33 and the version is server-wide, so one PHP 8
construct takes the whole site down. This catches it on your machine. (`deploy/cloudways.sh`
also compile-checks on the server and rolls back, but finding it locally is cheaper.)

---

## 9. Running the tests (optional)

Needs [Node 18+](https://nodejs.org/). Only the tests use it.

```bash
cd tests/browser
npm install                  # also downloads Chromium, ~150 MB
cd ../..
bash tests/browser/run.sh
```

`run.sh` **drops every table in the database named in `.env`** before each run and reloads
the demo seed, so keep it pointed at your local database. `tests/browser/README.md` lists
every suite.

---

## 10. Deploying

Live at **https://crm.syncworks.app** (Cloudways). Everything about production — the nginx +
PHP-FPM wiring, the panel settings, the deploy sequence, the PHP 7.4 constraint, the
rollback behaviour, troubleshooting — is in **[deploy/CLOUDWAYS.md](deploy/CLOUDWAYS.md)**.
Read it before touching the server.

The short version: a push to the deployed branch is the release. A cron runs
`deploy/auto-update.sh`, which compile-checks the new code with the server's own PHP and
restores the previous commit if anything fails to parse.

**The deploy cron is not enabled yet.** Until it is, deploys are manual:

```bash
cd ~/applications/dhrwuuxpcs/public_html && deploy/cloudways.sh update
```

---

## 11. Loading the real sales data

The sales team's spreadsheet has been exported to CSV. `deploy/CLOUDWAYS.md` §"Loading the
sales hand-off" has the full procedure. Two things to know before you run it:

- `schema/import-handoff.php --dry-run` performs the entire import inside a transaction and
  rolls it back, so the report comes from a real load. Always dry-run first.
- The import **clears the demo workspace first**. Unlike the seeder, it deletes. Never point
  it at a database holding data you want to keep.

`tests/fixtures/handoff/` holds a miniature version of the export, so the importer can be
exercised without the real data.

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| `'php' is not recognized` | PATH not set, or the terminal predates the change. Reopen it. |
| `could not find driver` | `extension=pdo_mysql` still commented out in `php.ini`. |
| `Access denied for user ''@'localhost'` | `.env` was not read. Check it sits next to `README.md` and the app can read it. |
| Every page says "Something went wrong" | Set `CI_ENVIRONMENT = development` in `.env` to see the real error, then look in `writable/logs/`. |
| Detail pages 404 locally, fine on the server | Server started without `dev/router.php`. See step 6. |
| Devanagari names show as `????` | Database or connection is not `utf8mb4`. Recreate the database as in step 3. |
| `/setup` instead of the sign-in page | No users exist — the seed did not run. Repeat step 5. |
