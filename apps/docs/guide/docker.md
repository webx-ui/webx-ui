# Docker from zero

A site on your own computer with nothing installed but Docker: no PHP, no Composer, no Node, no
database server. Written for somebody who has never done this — and for the AI agent working
beside them, so every step ends with a way to see that it worked.

You will end up with the site on `http://localhost:8080`, its admin panel on
`http://localhost:8080/cms`, and the mail it sends on `http://localhost:8025`.

::: tip Working with an agent
Give your agent this page and ask it to go step by step: run one step, show you the check, then
the next. Each command here is copied into a terminal as it is. Where a step fails, the
[table at the end](#when-a-step-fails) says what the message means.
:::

## 1. Install Docker Desktop

1. Download Docker Desktop from [docker.com](https://www.docker.com/products/docker-desktop/) for
   your system and install it. On Windows, accept WSL 2 when the installer offers it.
2. Start Docker Desktop and wait until it says the engine is running.
3. Open a terminal — **PowerShell** on Windows, **Terminal** on macOS or Linux — and run:

```bash
docker version
docker compose version
```

**Check:** both print a version, and `docker version` has a `Server:` part. Without it Docker
Desktop is not running yet.

## 2. Get the site

Go to the folder where you keep projects, then download the site skeleton. The site's folder is
the last word of the command — `my-site` here; use your own name, in lowercase letters, digits
and dashes.

```bash
docker run --rm -it -v "${PWD}:/app" -w /app composer:2 create-project webx-ui/site my-site --no-scripts --no-install
cd my-site
```

This runs Composer inside a container, once, and leaves only the site's files behind.
`--no-install` because the packages are installed inside the site's own container in step 5.

**Check:** the folder has `composer.json`, `Dockerfile`, `docker-compose.yml` and `.env.example`.

## 3. Write the settings file

Copy the example:

```bash
cp .env.example .env
```

Open `.env` in any text editor and change these lines. Passwords are anything long — letters and
digits, no spaces and no `#`:

```ini
APP_URL=http://localhost:8080

DB_HOST=database
DB_DATABASE=site
DB_USERNAME=site
DB_PASSWORD=choose-a-long-password

DB_ROOT_PASSWORD=choose-another-long-password
```

`DB_HOST=database` is the name of the database container. `DB_USERNAME` must not be `root`: the
database server creates this user for the site, and `root` is its own.

**Check:** the five `DB_` lines have values, and none of them is empty.

## 4. Prepare the front end

The site's container is built from the lock files of both halves. Composer's is made in step 5;
npm's is made now, again inside a throwaway container:

```bash
docker run --rm -v "${PWD}:/app" -w /app node:22-alpine npm install
```

**Check:** the folder now has `package-lock.json`.

## 5. Install the site

Three commands. Every `docker compose` command for this site names both compose files — the
second one turns the production setup into the one you work in.

Start the database on its own:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d database
```

Install the PHP packages and generate the application key. The first time, this builds the
site's image — five to fifteen minutes, once:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm --build --entrypoint sh app -c "composer install && php artisan key:generate"
```

Then the installer. It asks which modules the panel has (keep `admins` ticked — it is the sign-in), the database name (Enter keeps `site`),
the languages, whether to add demo content and the first administrator's email:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm --entrypoint sh app -c "php artisan webx:setup --name='My site' --domain=localhost:8080 --no-build"
```

`--no-build` because the front end is built by its own container in the next step. A yellow line
at the end about the front end not being built is expected.

**Check:** the installer ends with the panel's address, a login and a password. **Write the
password down** — it is printed once and stored nowhere.

## 6. Start the site

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

The first start installs the front end and prepares the database: a few minutes. Watch it:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f
```

`Ctrl+C` stops watching; the site keeps running.

**Check:** open `http://localhost:8080` — the site; `http://localhost:8080/cms` — the panel, sign
in with the email and password from step 5. Mail the site sends lands on `http://localhost:8025`.

## Every day

| What                       | Command                                                                                           |
| -------------------------- | ------------------------------------------------------------------------------------------------- |
| Stop the site              | `docker compose -f docker-compose.yml -f docker-compose.dev.yml stop`                             |
| Start it again             | `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d`                            |
| What is running            | `docker compose -f docker-compose.yml -f docker-compose.dev.yml ps`                               |
| Its log                    | `docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f app`                      |
| A site command (`artisan`) | `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec app php artisan …`           |
| Is everything wired        | `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec app php artisan webx:doctor` |

Stopping keeps everything. Docker Desktop shows the same containers under the folder's name, with
start and stop buttons.

Files you edit in the folder are what the site runs: a change to a view or a stylesheet shows on
the next page load — [Where the styles live](./styles.md) says which files those are.

## Where the data lives

| What                                | Where                                                                        |
| ----------------------------------- | ---------------------------------------------------------------------------- |
| The site's code, views and styles   | the folder itself — keep it in git                                           |
| Uploaded files, logs, nightly dumps | `storage/` in the folder (dumps under `storage/app/private/backups`)         |
| The database                        | a Docker volume named `<folder>_database-data` (`docker volume ls` lists it) |
| Settings and passwords              | `.env` in the folder — never commit it                                       |

`docker compose … down` removes the containers and keeps the volume. **`down -v` deletes the
database for good** — only on a site you are throwing away. A copy of the database at any moment:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec app php artisan webx:db:backup
```

## When a step fails

| You see                                                             | What it means and what to do                                                                                                                                              |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Cannot connect to the Docker daemon`, `error during connect`       | Docker Desktop is not running. Start it, wait for "running", repeat the step.                                                                                             |
| `DB_PASSWORD is required` or `DB_ROOT_PASSWORD is required`         | `.env` misses one of them — step 3.                                                                                                                                       |
| The database container restarts, its log says `MARIADB_USER="root"` | `DB_USERNAME=root` in `.env`. Use another name; then `docker compose … down -v` (the database is still empty) and step 5 again.                                           |
| `Access denied for user` after changing a password in `.env`        | The database was created with the old one, and it keeps it. On a new site: `down -v` and step 5 again. On a site with content, change the password back.                  |
| A build error ending in `"/package-lock.json": not found`           | Step 4 was skipped.                                                                                                                                                       |
| `port is already allocated` / `address already in use`              | Something else uses the port. Set `APP_PORT=8081` (or `VITE_PORT`, `MAILPIT_PORT`, `DB_PORT_PUBLISHED`) in `.env`, and use that port in the addresses.                    |
| The page loads without styles, or the panel is blank                | The front-end container is still installing, or stopped. `docker compose … logs vite` — wait for `ready`, or `up -d` again.                                               |
| `The database did not answer within 120s`                           | The database is still starting on a slow machine, or the user in `.env` differs from the one it was created with — see `Access denied` above. `logs database` says which. |
| `No answer from database:3306` in the installer                     | The database is not up: run the first command of step 5 again and wait half a minute.                                                                                     |
| Everything is very slow on Windows                                  | Files shared from Windows into Linux containers are slow. Keeping the folder inside WSL (`\\wsl$\…`) is several times faster; the commands are the same.                  |

Anything else: run `webx:doctor` (the "Every day" table) — it names what is misconfigured and how
to fix it — and give your agent the last lines of `docker compose … logs app`.

## When the site goes to a server

The same folder runs in production with one file instead of two:

```bash
docker compose up -d --build
```

That builds one image with everything compiled in, beside the database. What it needs on the
server — a proxy in front for HTTPS, the variables at the bottom of `.env.example` — is in
[A new site → Containers](./new-site.md#containers).
