# Cart Scheduler

***

# Please Note, these directions assume you are a developer and understand advanced concepts such as hosting, setting up a database, and using the command line. If these concepts are foreign to you, please find someone who can help you.

***

## Deployment Issues

For some reason, the deployment has an issue with the `vendor` directory. Specifically around the Laravel Excel package.
If the Download Users feature fails, delete the vendor package and run `composer install` again:

```bash
rm -rf vendor && composer install
```

## Deployment

### Downloading and Extracting the Release

You can [download the latest release here](https://github.com/pixelated-au/CartScheduler/releases).
If using the command line, you can download the release using `wget`:

```bash
wget https://github.com/pixelated-au/CartScheduler/releases/latest/download/release.zip
```

Extract the release to a directory on your server. For example you cauld name the directory `smpw_app`
or `cart-scheduler`.

### cPanel Hosting

cPanel Hosting is a little problematic because the root domain typically needs to be served from the directory
`~/public_html`. This means the default laravel directory structure which is served from `/public`. If you are serving
from an addon domain, it's easier because you can setup a directory structure to suit. For example, you could setup a
domain `mydomain.example.com` and set the directory to `~/mydomain.example.com/public`.

Alternatively, you can setup a simlink to the `public` directory. In the example below, replace `[MY ACCOUNT]` with your
cPanel account name:

```bash
mv public_html public_html.bak && ln -s /home/[MY ACCOUNT]/smpw_app/public /home/[MY ACCOUNT]/public_html
```

> [!IMPORTANT]
> :warning: If you are using cPanel, in the instructions below, you may need to replace the default PHP version with PHP 8.x by
> replacing `php` with `ea-php82` in the commands below. You can check the PHP version by running `php -v`. If the
> version is below 8.1, you will need to use the `ea-php82` command. An example is provided below

### Standard Deployment

To deploy a release requires the following steps:

1. Download the release from the [releases page](https://github.com/pixelated-au/CartApp/releases)
1. Extract the release to a folder on the server
1. *Note: Make sure you are running PHP 8.1 or higher*
1. Unzip the release.
    - If using cPanel, you can do this via the File Manager
1. Copy the `.env.example` file to `.env` and update the values as required.
    - For cPanel, you can often use sendmail as the mail server. The following settings often work in the mail section:
    - ```dotenv
      MAIL_MAILER=sendmail
      MAIL_HOST=localhost
      ```
1. SSH into the server if you haven't yet already.
1. Make sure the permissions on the installation direcotry are set to 775 and files are set to 644. Navigate to the installed directory and use this:
    ```bash
    find . -type d -exec chmod 775 {} \; && find . -type f -exec chmod 644 {} \;

    ```
1. Run the following command to enable the Laravel cache:
    ```bash
    php artisan key:generate \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache \
    && php artisan storage:link
    ```
1. Or for cPanel, if the default system PHP version is not PHP 8.1, you may be able to use the following:
    ```bash
    ea-php82 artisan key:generate \
    && ea-php82 artisan config:cache \
    && ea-php82 artisan route:cache \
    && ea-php82 artisan view:cache \
    && ea-php82 artisan event:cache \
    && ea-php82 artisan storage:link
    ```    

> [!TIP]
> Note: If you are having issues with something not working, it's likely because 'something' has been cached. You may need to run the following command to clear it:_
> ```bash
> php artisan optimize:clear
> ```

1. Run the following command to migrate/install the database:
    ```bash
    php artisan migrate
    ```
1. Setup the cron job to run the following command every minute:
    ```bash
    php artisan schedule:run >> /dev/null 2>&1
    ```
        - eg: `* * * * * cd (/home/[MY_ACCOUNT]/[MY_APP] && php artisan schedule:run >> /dev/null 2>&1)`
          <- Note, you may need to replace `php` with `ea-php82` if you're using cPanel
1. Create the admin user. Note, this can only be run once.:
```bash
php artisan cart-scheduler:create-user "<name>" <email> <phone> <gender> [<password>]
```
1. Navigate to the site and login with the admin user you just created.
    - If you are having a server issue (eg 500 error), review the log file at `storage/logs/laravel.log`.
    - If you have this issue: `Your serialized closure might have been modified or it's unsafe to be unserialized`,
      you may need to run the following command: `php artisan route:clear`
   
## Development

This section is for development only and is not required to run the application on a live server.

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

For development, the system uses [MailHog](https://github.com/mailhog/MailHog) that can be accessed
at http://localhost:8025. All emails sent from the system
will be available in this interface.

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
