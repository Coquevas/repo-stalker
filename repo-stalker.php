#! /usr/bin/env php
<?php
/* TODO
 * - Something better than "all in one file"
 * - Error handling
 * - Extract all the loops
 * - Stop passing authentication on every call
 */

require_once __DIR__ . '/vendor/autoload.php';

list($theRepoName, $theRepoOwner, $theUser, $thePassword) = configuration_dialog();

$theRepo = "$theRepoOwner/$theRepoName";
$itemsPerPage = 100;

fwrite(STDOUT, "Gathering repo information..." . PHP_EOL);
$uri = "https://api.github.com/repos/$theRepo";
$response = \Httpful\Request::get($uri)->authenticateWith($theUser, $thePassword)->send();
$numberOfStargazersPages = ceil($response->body->stargazers_count / $itemsPerPage);
$numberOfForksPages = ceil($response->body->forks_count / $itemsPerPage);
$theLanguage = $response->body->language;


fwrite(STDOUT, "Gathering repo's stargazers..." . PHP_EOL);
$repositories = array();
$progressBar = new \ProgressBar\Manager(0, $numberOfStargazersPages);
for ($i = 1; $i <= $numberOfStargazersPages; $i++) {
    $uri = "https://api.github.com/repos/$theRepo/stargazers?page=$i&per_page=$itemsPerPage";
    $response = \Httpful\Request::get($uri)->authenticateWith($theUser, $thePassword)->send();
    foreach ($response->body as $user) {
        $repositories[$user->login] = $user->repos_url;
    }
    $progressBar->update($i);
}

fwrite(STDOUT, "Gathering repo's forks..." . PHP_EOL);
$progressBar = new \ProgressBar\Manager(0, $numberOfForksPages);
for ($i = 1; $i <= $numberOfForksPages; $i++) {
    $uri = "https://api.github.com/repos/$theRepo/forks?page=$i&per_page=$itemsPerPage";
    $response = \Httpful\Request::get($uri)->authenticateWith($theUser, $thePassword)->send();
    foreach ($response->body as $repo) {
        $repositories[$repo->owner->login] = $repo->owner->repos_url;
    }
    $progressBar->update($i);
}

fwrite(STDOUT, "Gathering users reputation..." . PHP_EOL);
$rockStars = array();
$progressBar = new \ProgressBar\Manager(0, count($repositories));
foreach ($repositories as $user => $url) {
    $response = \Httpful\Request::get($url)->authenticateWith($theUser, $thePassword)->send();
    foreach ($response->body as $repo) {
        if (!$repo->fork && $repo->language == $theLanguage) {
            @$rockStars[$user] += $repo->stargazers_count;
        }
    }
    $progressBar->advance();
}

asort($rockStars);
var_export($rockStars);

function configuration_dialog() {
    $theRepoName = prompt("Repo name: ");
    $theRepoOwner = prompt("Repo owner: ");
    $username = prompt("Your GitHub username: ");
    $password = prompt_silent("Your GitHub password: ");
    return array($theRepoName, $theRepoOwner, $username, $password);
}

/**
 * @see http://www.thecave.info/php-stdin-command-line-input-user/
 */
function read_stdin() {
    $fr = fopen("php://stdin", "r");
    $input = fgets($fr, 128);
    $input = rtrim($input);
    fclose($fr);
    return $input;
}

function prompt($prompt) {
    fwrite(STDERR, $prompt);
    return read_stdin();
}

/**
 * @param string $prompt
 * @return string|void
 * @see https://www.sitepoint.com/interactive-cli-password-prompt-in-php/
 */
function prompt_silent($prompt = "Enter Password:") {
    if (preg_match('/^win/i', PHP_OS)) {
        $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
        file_put_contents(
            $vbscript, 'wscript.echo(InputBox("'
            . addslashes($prompt)
            . '", "", "password here"))');
        $command = "cscript //nologo " . escapeshellarg($vbscript);
        $password = rtrim(shell_exec($command));
        unlink($vbscript);
        return $password;
    } else {
        $command = "/usr/bin/env bash -c 'echo OK'";
        if (rtrim(shell_exec($command)) !== 'OK') {
            trigger_error("Can't invoke bash");
            return;
        }
        $command = "/usr/bin/env bash -c 'read -s -p \""
            . addslashes($prompt)
            . "\" mypassword && echo \$mypassword'";
        $password = rtrim(shell_exec($command));
        echo "\n";
        return $password;
    }
}
