<?php

const DAY_CONTAINS_HOURS = 24;
const WEEK_CONTAINS_DAYS = 7;
const HOUR_COLUMN_WIDTH = 5;
const DAY_OF_WEEK_WIDTH = 11;

const PROTOCOL_PREFIX = "https://";
const API_URL = "api.github.com";
const USERS = 'users';
const REPOS = 'repos';
const COMMITS = 'commits';
const PAGE_SIZE = 100;

const ID = '4053fb491a354a6cb61f';
const WORD = '649b38ede0df261a20a5da78c6ea27c263922b1a';

const SETTING_ACCOUNT = 'account';
const SETTING_AUTHOR = 'author';
const SETTING_AUTHENTICATION = 'authentication';
const SETTING_DOWNLOAD_OPTIONS = 'downloadOptions';

const ACCOUNT_OPTION = 'a';
const NAME_OPTION = 'n';

$optionsSet = ACCOUNT_OPTION . ':' . NAME_OPTION . ':';
$options = getopt($optionsSet);

$isAuthorSet = isset($options[NAME_OPTION]);
$author = '';
if ($isAuthorSet) {

    $author = $options[NAME_OPTION];
}

$isAccountSet = isset($options[ACCOUNT_OPTION]);

if (!$isAccountSet) {
    echo "
please set Github account : -a <github_account_name>
also you may set commit author name : -a <github_account_name> [-n commit_author_name]";
}

if ($isAccountSet) {

    $account = $options[ACCOUNT_OPTION];

    showGithubMetrics($account, $author);
}


function showGithubMetrics(string $account, string $author)
{

    $downloadOptions = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP'
            ]
        ]
    ];
    $authentication = 'client_id=' . ID . '&client_secret=' . WORD;

    $handleSetting = setSettings($account, $author, $authentication, $downloadOptions);

    $repositoryNamesList = getRepositoriesNames($handleSetting);
    $dates = getCommitsStatics($handleSetting, $repositoryNamesList);

    $grafs = array();
    foreach ($dates as $key => $commitDates) {


        foreach ($commitDates as $date) {
            $dateWeekDay = intval(date('w', strtotime($date)));
            $dateHour = intval(date('H', strtotime($date)));

            $isSet = isset($grafs[$key] [$dateWeekDay] [$dateHour]);
            if (!$isSet) {
                $grafs[$key] [$dateWeekDay] [$dateHour] = 0;
            }

            $grafs[$key] [$dateWeekDay] [$dateHour] += 1;
        }

    }

    date_default_timezone_set('UTC');

    foreach ($grafs as $key => $graf) {

        $commitCount = 0;
        foreach ($graf as $dayCommits) {
            foreach ($dayCommits as $hoursCommits) {
                $commitCount += $hoursCommits;
            }

        }

        echo "\n\nREPOSITORY : $key, commits number $commitCount\n";
        echo "\nDAY OF WEEK";

        for ($day = 0; $day < WEEK_CONTAINS_DAYS; $day++) {

            echo "\n" . str_pad(date('l', strtotime("Sunday +{$day} days")) . ' |', DAY_OF_WEEK_WIDTH, ' ', STR_PAD_LEFT);

            for ($hour = 0; $hour < DAY_CONTAINS_HOURS; $hour++) {

                $hasHour = isset($graf[$day][$hour]);

                $point = '';
                if ($hasHour) {
                    $point = $graf[$day][$hour];
                }

                $string = str_pad($point . ' |', HOUR_COLUMN_WIDTH, ' ', STR_PAD_LEFT);

                echo $string;

            }
        }

        echo "\n" . str_pad('HOUR |', DAY_OF_WEEK_WIDTH, ' ', STR_PAD_LEFT);

        for ($hour = 0; $hour < DAY_CONTAINS_HOURS; $hour++) {

            echo str_pad(date('H', strtotime("$hour hours", 0)) . ' |', HOUR_COLUMN_WIDTH, ' ', STR_PAD_LEFT);

        }
        echo "\n";

    }
}

/**
 * @param string $account
 * @param string $author
 * @param string $authentication
 * @param array $downloadOptions
 * @return array
 */
function setSettings(string $account, string $author, string $authentication, array $downloadOptions): array
{
    $handleSettings[SETTING_ACCOUNT] = $account;
    $handleSettings[SETTING_AUTHOR] = $author;
    $handleSettings[SETTING_AUTHENTICATION] = $authentication;
    $handleSettings[SETTING_DOWNLOAD_OPTIONS] = $downloadOptions;

    return $handleSettings;
}
/**
 * @param array $settings
 * @return array
 */
function getSettings(array $settings): array
{
    $account = $settings[SETTING_ACCOUNT];
    $author = $settings[SETTING_AUTHOR];
    $authentication = $settings[SETTING_AUTHENTICATION];
    $downloadOptions = $settings[SETTING_DOWNLOAD_OPTIONS];


    return array($account, $author, $authentication, $downloadOptions);
}

/**
 * @param array $settings
 * @param $repositoryNamesList
 * @return array
 */
function getCommitsStatics(array $settings, $repositoryNamesList): array
{
    list($account, $author, $authentication, $downloadOptions) = getSettings($settings);

    $streamContext = stream_context_create($downloadOptions);
    $commitsInformationList = array();
    foreach ($repositoryNamesList as $name) {

        $sourceBase =
            PROTOCOL_PREFIX
            . API_URL
            . '/'
            . REPOS
            . '/'
            . $account
            . '/'
            . $name
            . '/'
            . COMMITS
            . '?'
            . $authentication
            . '&per_page='
            . PAGE_SIZE;
        $page = 0;

        do {
            $page++;
            $source = $sourceBase . "&page=$page";
            $rawCommitsInformation = file_get_contents($source, false, $streamContext);
            $commitsInformation = json_decode($rawCommitsInformation, true);

            $isExists = isset($commitsInformationList[$name]);
            if (!$isExists) {
                $commitsInformationList[$name] = array();
            }

            $numbers = count($commitsInformation);

            $isContain = $numbers > 0;
            if ($isContain) {
                $commitsInformationList[$name] = array_merge($commitsInformationList[$name], $commitsInformation);
            }


        } while ($isContain);
    }

    $performValidation = !empty($author);

    $dates = array();
    foreach ($commitsInformationList as $key => $commits) {

        foreach ($commits as $commit) {
            $isValid = true;
            if ($performValidation) {
                $authorName = $commit['commit']['author']['name'];
                $isValid = $author == $authorName;
            }

            if ($isValid) {
                $dates[$key][] = $commit['commit']['author']['date'];
            }
        }


    }

    return $dates;
}

/**
 * @param array $setting
 * @return array
 * @internal param string $account
 * @internal param $authentication
 * @internal param $downloadOptions
 */
function getRepositoriesNames(array $setting): array
{
    list($account, , $authentication, $downloadOptions) = getSettings($setting);

    $streamContext = stream_context_create($downloadOptions);
    $repositoriesRawList = file_get_contents(PROTOCOL_PREFIX . API_URL . '/' . USERS . '/' . $account . '/' . REPOS . '?' . $authentication, false, $streamContext);

    $repositoriesList = json_decode($repositoriesRawList, true);

    unset($repositoriesRawList);

    $repositoryNamesList = array();
    foreach ($repositoriesList as $information) {
        $name = $information['name'];
        $repositoryNamesList[] = $name;
    }

    return $repositoryNamesList;
}
