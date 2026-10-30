# Cart Scheduler

***

# Please Note, these directions assume you are a developer and understand advanced concepts such as hosting, setting up a database, and using the command line. If these concepts are foreign to you, please find someone who can help you.

***

## Deployment

Releases are deployed with [Deployer](https://deployer.org) (v8), run from your own computer over SSH. Each deployment
downloads a GitHub release's `release.zip`, installs the Composer dependencies pinned in `composer.lock`, migrates the
database and then switches the site over in one step. The previous release stays on disk, so a broken deployment can be
rolled back in seconds.

On the server, each site ends up looking like this:

```
~/cartapp/
  releases/41/  42/  43/        ← the 5 most recent releases
  shared/.env                   ← your configuration, shared by every release
  shared/storage/               ← uploads, logs and cache, shared by every release
  current -> releases/43        ← the live release; the web server serves current/public
```

Each release also keeps the front-end assets of the releases before it, so volunteers with an older page still open
(or cached) keep working after an update.

### Requirements

- **Server:** PHP 8.3 or higher, MySQL or MariaDB, SSH access, and the `curl` and `unzip` commands. Composer is not
  required; Deployer downloads it if it's missing.
- **Your computer:** PHP 8.3 or higher and Composer. Install Deployer with:
    ```bash
    composer global require deployer/deployer:^8
    ```

### 1. Describe your sites

Copy `hosts.yml.example` to `hosts.yml` (it is git-ignored) and add an entry per site. Each site needs SSH key access
for its account, a `deploy_path`, and an `env` label of `staging` or `prod`:

```yaml
hosts:
  my-site:
    hostname: server1.example.org
    remote_user: myaccount
    deploy_path: ~/cartapp
    labels:
      env: prod
```

> [!IMPORTANT]
> On cPanel, the default `php` command is often not PHP 8.3. Add `bin/php: /opt/cpanel/ea-php83/root/usr/bin/php`
> (or the path for your version) to the host so that Composer and Artisan use the right PHP.

### 2. Point the website at `current/public`

The web server must serve `~/cartapp/current/public`.

- **Addon domain or subdomain:** set the domain's document root to `cartapp/current/public`.
- **Main cPanel domain, or any document root you can't change:** add `webroot` to the host in `hosts.yml`, set to
  the directory the web server already serves:
    ```yaml
        webroot: ~/public_html
    ```
  Every deployment keeps that path as a symlink to `current/public`, and `dep rollback` moves it too. If it's still a
  real directory, the deployment stops before changing anything. Move the directory aside first:
    ```bash
    mv ~/public_html ~/public_html.bak
    ```

### 3. Create the configuration (new sites only)

Create the directory structure, then upload `.env.example` as the shared `.env` and edit it:

```bash
dep deploy:setup my-site
scp .env.example myaccount@server1.example.org:~/cartapp/shared/.env
```

In `shared/.env`, set at least the `APP_*`, `DB_*` and `MAIL_*` values and `QUEUE_CONNECTION=database`. `APP_KEY`
needs a random key, which you can generate with:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

On cPanel, sendmail usually works for mail:

```dotenv
MAIL_MAILER=sendmail
MAIL_HOST=localhost
```

### 4. Deploy

```bash
dep deploy my-site --pre           # latest release, including beta releases
dep deploy my-site --tag=v3.5.0b   # a specific release
dep deploy env=prod --pre          # every production site
dep deploy                         # no site given: deploys to the env=staging sites only
```

Deployer shows the version it found and asks for confirmation before changing anything. Without `--tag` or `--pre`
it picks the latest stable (non-beta) release. It refuses to install a version older than the one already installed;
use `dep rollback` for that, or pass `--allow-downgrade`.

Other useful commands:

```bash
dep rollback my-site   # switch back to the previous release (database migrations are not reversed)
dep releases my-site   # list the deployed releases and their versions
dep ssh my-site        # open a shell in the live release
```

### 5. Finish setting up a new site

1. Set up a cron job to run the scheduler every minute. On cPanel, replace `php` with the PHP path from step 1:
    ```bash
    * * * * * cd ~/cartapp/current && php artisan schedule:run >> /dev/null 2>&1
    ```
1. All emails are sent from the queue. The scheduler cron above also starts a queue worker every minute, so cPanel
    and other cron-only hosts need nothing else. Don't add a separate `queue:work` cron as well.
    If Supervisor (or Forge, systemd) runs a permanent worker instead, set `CA_RUN_QUEUE_WORKER_FROM_SCHEDULER=false`;
    each deployment restarts it automatically.
    **Admin → Queue** shows whether the scheduler and worker are running, and lets you retry failed jobs.
    **Admin → Notifications** lists every email sent. Local development can leave `QUEUE_CONNECTION=sync`.
1. Create the admin user. Note, this can only be run once. Use `dep ssh my-site`, then:
    ```bash
    php artisan cart-scheduler:create-user "<name>" <email> <phone> <gender> [<password>]
    ```
1. Navigate to the site and login with the admin user you just created.

### Moving an existing installation to Deployer

Use a new `deploy_path` that isn't the old installation's directory, such as `~/cartapp` alongside `~/smpw_app`.
The old installation then keeps serving the site until the switch, and switching back is one command.

1. Run `dep deploy:setup my-site`.
1. Copy the existing `.env` to `~/cartapp/shared/.env`, and the existing `storage` directory to
    `~/cartapp/shared/storage`.
1. If the site uses `webroot` (step 2), leave it out of `hosts.yml` for now so the old installation stays live.
1. Deploy the version the site is already running: `dep deploy my-site --tag=<version>`. Using the same version
    means no migrations run while the old installation is still live.
1. Switch the website over:
    - **With `webroot`:** move the old symlink or directory aside (`mv ~/public_html ~/public_html.bak`), add
      `webroot` to the host, and deploy the same version again.
    - **Otherwise:** change the domain's document root to `cartapp/current/public`.
1. Change the cron job's path (step 5).
1. To switch back, point the web root at the old installation again, for example
    `rm ~/public_html && mv ~/public_html.bak ~/public_html`, and restore the cron job.
1. Once the site is working, delete the old installation directory and the `.bak`.

### Troubleshooting

- If you have a server issue (eg 500 error), review the log file at `~/cartapp/shared/storage/logs/laravel.log`.
- If something isn't working, it's likely because 'something' has been cached. From `dep ssh`, run
  `php artisan optimize:clear`.
- If you have this issue: `Your serialized closure might have been modified or it's unsafe to be unserialized`, run
  `php artisan route:clear`.
- To reinstall a release, including all of its Composer dependencies, deploy the same version again.

## Development

This section is for development only and is not required to run the application on a live server.

### Branches and releases

Each major version has its own long-lived branch (`v4`, `v5`, …). It is created when work on that version starts and
is never deleted. These are often called release or maintenance branches.

- **`master`** is the development branch for the current stable major version, which is v4 for now. New work branches
  off `master`, and its pull requests target `master`.
- **`vX`** (for example `v4`) is where that version's releases are tagged. When `master` is ready for a release, merge
  it into `v4` and tag the release on `v4`.
- **Starting the next major version:** create `v5` from `master`. Work for v5 then branches off and targets `v5`, while
  `master` stays on the stable version (v4).
- **Carrying v4 changes into v5:** anything added to v4 after `v5` was created must also reach v5. Merge `v4` into
  `v5` to do this, which is called forward-porting. Use a merge rather than a rebase: `v5` is shared, and rebasing it
  rewrites published commits, which needs a force-push and breaks everyone else's copy of the branch.
- **When v5 becomes stable,** merge `v5` into `master`, so `master` becomes the development branch for v5.

Release tags follow the version and its stage. The release workflow marks betas and release candidates as GitHub
pre-releases automatically:

| Stage             | Tag example | GitHub release | Deploy with                        |
|-------------------|-------------|----------------|------------------------------------|
| Beta              | `v4.0.0b`   | Pre-release    | `dep deploy … --pre` or `--tag=…`  |
| Release candidate | `v4.0.0rc1` | Pre-release    | `dep deploy … --pre` or `--tag=…`  |
| Stable            | `v4.0.0`    | Latest         | `dep deploy …`                     |

v3 was only released as betas and has no stable release. v4 is the next stable version.

### Vue Devtools

Vue devtools is enabled in development. You can configure the 'component inspector' to open the respective component
in your editor by setting the `LAUNCH_EDITOR` [environment variable](https://github.com/yyx990803/launch-editor?tab=readme-ov-file#custom-editor-support):

```env
LAUNCH_EDITOR=
```

### TypeScript
This project is now using TypeScript with Vue.

### Frontend Packages Size
To ensure the project doesn't get out of control, utilise `npx vite-bundle-visualizer` to visualise the size of the
frontend packages and see if there's anything that can be removed. 

### Ziggy
For synchronicity between Laravel Routes and Vue, we use [Ziggy](https://github.com/tighten/ziggy).

The Ziggy types can be generated via the composer script called `ziggy`:
#### Laravel Sail:
```bash
sail composer ziggy
```
#### CLI:
```bash
php composer ziggy
```


### Email Testing

For development, `.env.example` points at [Mailpit](https://github.com/axllent/mailpit) (`MAIL_HOST=mailpit`, port `1025`).
Open http://localhost:8025 to inspect captured mail. Production should override these (e.g. SES).

### Notes:

- If the link to `/storage/example-user-import.xlsx` isn't working (found on the admin, import users page), make sure
  the symlink has been created. Run `php artisan storage:link` to create the symlink.

## TODO:

- [ ] Add support for an arbitrary in the report comments. Should be configurable in the admin.
- [ ] Add Content Security Policy (CSP)
- [ ] If admin enters a wrong email address and it generates a \Symfony\Component\Mailer\Exception\UnexpectedResponseException, send a notification to the admin
- [ ] If a user sets their availability to being not available but admin has assigned them a shift before the shift is released, notify the admin. If the shift is released, notify the volunteer.
- [ ] Add flag to highlight a shift hasn't opened yet
- [ ] Make locations and shifts sortable
- [ ] Add selected dates to the shifts, so rather than entering a range, admin can enter individual dates
- [ ] Facility to detect if a volunteer's 'availability' conflicts with a shift
- [ ] Facility to detect if a shift lost a volunteer after removing themselves - and causing the shift to be closed
- [ ] Test that if a shifts can't be defined at the same day/time unless there is a start and end date that doesn't
  overlap
- [ ] Improve the reports table
- [ ] Make publication reporting optional
- Reports:
    - [ ] Make reporting of literature optional
    - [ ] Feature to disable the reporting feature
    - [ ] Show restricted volunteers
    - [ ] Show who has outstanding reports
- [x] Temporary volunteer
- [x] Implement feature for admin to remove a user from a shift in addition to moving them to another shift
    - [ ] Create relationships between users so admin can assign user and their relationship to a shift
- [ ] Allow users to book another user onto a shift
- [ ] Support dark mode for toast notifications
- [ ] Test that disabled users are removed from future shifts
- [ ] Test that if a disabled shift is re-enabled, it doesn't conflict with existing shifts
- [ ] Feature to flag shifts as not self-bookable
- [ ] Content pages feature
- [ ] Day overseer feature
- [ ] User tags feature
- [ ] Notifications feature
- [ ] If a location or a shift is "re-enabled", the system should first look for schedule conflicts and show those to the admin
- [ ] Make a 'trainer' user type and allow them to un-restrict users who are with them on a shift. This feature needs to be configurable
- [ ] In admin, if 'location choices' is enabled, add location filter to the find volunteers dialog
- [ ] Add capability for admin to self-publish shifts per location. This will override automatic publishing of shifts per location
- [ ] Admin controlled feature to limit how many shifts a user can be assigned to
