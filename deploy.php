<?php

/**
 * Deployer recipe for Cart Scheduler (https://deployer.org, v8).
 *
 * Deploys a GitHub release (release.zip built by CI) into releases/<n>, shares .env and storage/,
 * installs Composer dependencies from composer.lock, migrates, then atomically switches `current`
 * and runs `artisan reload` (which restarts the queue workers).
 * The web server docroot must point at {{deploy_path}}/current/public.
 *
 * Usage:
 *   dep deploy                          latest stable release to the default selector (staging)
 *   dep deploy env=prod                 latest stable release to every production host
 *   dep deploy env=staging --pre        latest release, including pre-releases
 *   dep deploy smpw-north --tag=v3.4.2  a specific release to one host
 *   dep rollback env=prod               point `current` back at the previous release
 *
 * Hosts are defined in hosts.yml, which is not committed. Copy hosts.yml.example to get started.
 */

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

require 'recipe/laravel.php';

set('application', 'Cart Scheduler');
set('github_repository', 'CartScheduler/CartScheduler');
set('keep_releases', 5);
set('default_selector', 'env=staging');

// Each account owns its own files and PHP runs as that account, so no permission changes are needed.
set('writable_mode', 'skip');

option('pre', null, InputOption::VALUE_NONE, 'Allow the latest pre-release when no --tag is given');
option('allow-downgrade', null, InputOption::VALUE_NONE, 'Allow deploying a version older than the one installed');

import(__DIR__.'/hosts.yml');

function latestReleaseTag(bool $allowPreRelease): string
{
    $api = 'https://api.github.com/repos/'.get('github_repository').'/releases';
    $headers = ['Accept' => 'application/vnd.github+json'];

    if (! $allowPreRelease) {
        // GitHub's "latest" release excludes drafts and pre-releases.
        return json_decode(fetch("$api/latest", headers: $headers), true)['tag_name'];
    }

    foreach (json_decode(fetch("$api?per_page=20", headers: $headers), true) as $release) {
        if (! $release['draft']) {
            return $release['tag_name'];
        }
    }

    throw error('No published releases found.');
}

/**
 * Resolves and confirms the release once for the whole run, so every host gets the same version.
 * It runs on the first selected host only, before any host is locked or gets a release directory.
 */
desc('Resolves and confirms the release to deploy');
task('deploy:release_tag', function () {
    $tag = input()->getOption('tag') ?: latestReleaseTag((bool) input()->getOption('pre'));

    if (! askConfirmation("Deploy $tag to: ".implode(', ', get('selected_hosts')).'?', true)) {
        throw error('Deployment cancelled.');
    }

    set('release_tag', $tag);
})->once();

// Each host runs in its own worker process, so read the tag back from the host that resolved it.
set('target', function () {
    $resolvedOn = selectedHosts()[0];
    $resolvedOn->config()->load();

    return $resolvedOn->get('release_tag');
});
set('what', '{{application}} {{target}}');

desc('Refuses to install a version older than the one currently installed');
task('deploy:check_version', function () {
    if (input()->getOption('allow-downgrade') || ! test('[ -f {{deploy_path}}/current/VERSION ]')) {
        return;
    }

    $installed = trim(run('cat {{deploy_path}}/current/VERSION'));
    $target = get('target');

    if (version_compare(ltrim($target, 'v'), ltrim($installed, 'v'), '<')) {
        throw error("$target is older than the installed $installed. Use `dep rollback`, or pass --allow-downgrade.");
    }
});

desc('Downloads and extracts the GitHub release');
task('deploy:update_code', function () {
    $tag = get('target');
    $url = 'https://github.com/'.get('github_repository')."/releases/download/$tag/release.zip";

    cd('{{release_path}}');
    run('curl -fsSL -o release.zip '.escapeshellarg($url));
    run('unzip -q release.zip && rm release.zip');
    run('echo '.escapeshellarg($tag).' > VERSION');

    if (! test('[ -f composer.lock ]')) {
        throw error("release.zip for $tag has no composer.lock; refusing to resolve dependencies.");
    }
});

/**
 * Keeps the built assets of recent releases available, so browsers holding a cached page from an
 * earlier release can still load its JS/CSS chunks. Vite file names are content-hashed, so builds
 * never overwrite each other.
 *
 * Each release records the asset files it built in .build-assets. Assets carried forward from the
 * previous release are kept only while a release that built them is among the newest keep_releases.
 */
desc('Carries recent releases\' built assets forward and prunes older ones');
task('deploy:carry_assets', function () {
    $assets = '{{release_path}}/public/build/assets';
    run("ls -1 $assets > {{release_path}}/.build-assets");

    if (! has('previous_release')) {
        return;
    }

    run("for f in {{previous_release}}/public/build/assets/*; do [ -e $assets/\"\${f##*/}\" ] || cp -p \"\$f\" $assets/; done");

    $releases = array_slice(explode("\n", run('cd {{deploy_path}}/releases && ls -t -1')), 0, (int) get('keep_releases'));

    $keep = [];
    foreach ($releases as $release) {
        $list = "{{deploy_path}}/releases/$release/.build-assets";
        if (! test("[ -f $list ]")) {
            // A kept release predates this task, so it is unknown which assets it needs.
            return;
        }
        array_push($keep, ...explode("\n", run("cat $list")));
    }

    $stale = array_diff(explode("\n", run("ls -1 $assets")), $keep);
    foreach (array_chunk($stale, 100) as $files) {
        run("cd $assets && rm -f ".implode(' ', array_map('escapeshellarg', $files)));
    }
});

before('deploy:info', 'deploy:release_tag');
before('deploy:lock', 'deploy:check_version');
after('deploy:vendors', 'deploy:carry_assets');
after('deploy:failed', 'deploy:unlock');
