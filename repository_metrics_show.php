<?php

const ACCOUNT_ARGUMENT_INDEX = 1;

const DAY_CONTAINS_HOURS = 24;
const WEEK_CONTAINS_DAYS = 7;
const HOUR_COLUMN_WIDTH = 5;
const DAY_OF_WEEK_WIDTH = 11;

const PROTOCOL_PREFIX = "https://";
const API_URL = "api.github.com";
const USERS = 'users';
const REPOS = 'repos';
const COMMITS = 'commits';

const ID = '4053fb491a354a6cb61f';
const WORD = '649b38ede0df261a20a5da78c6ea27c263922b1a';


const SETTING_ACCOUNT = 'account';
const SETTING_AUTHENTICATION = 'authentication';
const SETTING_DOWNLOAD_OPTIONS = 'downloadOptions';

$isSet = isset($argv[ACCOUNT_ARGUMENT_INDEX]);

if ($isSet) {
    $account = $argv[ACCOUNT_ARGUMENT_INDEX];
    showGithubMetrics($account);
}

if (!$isSet) {
    echo "please set Github account name, script using : $argv[0] <github_account_name>";
}

function showGithubMetrics(string $account)
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

    $handleSetting = setSettings($account, $authentication, $downloadOptions);

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

            $grafs[$key] [$dateWeekDay] [$dateHour] += ACCOUNT_ARGUMENT_INDEX;
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
 * @param $authentication
 * @param $downloadOptions
 * @return array
 * @internal param $handleSettings
 */
function setSettings(string $account, string $authentication, array $downloadOptions): array
{
    $handleSettings[SETTING_ACCOUNT] = $account;
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
    $authentication = $settings[SETTING_AUTHENTICATION];
    $downloadOptions = $settings[SETTING_DOWNLOAD_OPTIONS];
    return array($account, $authentication, $downloadOptions);
}

/**
 * @param array|string $settings
 * @param $repositoryNamesList
 * @return array
 * @internal param $downloadOptions
 * @internal param $authentication
 */
function getCommitsStatics(array $settings, $repositoryNamesList): array
{
    list($settings, $authentication, $downloadOptions) = getSettings($settings);

    $streamContext = stream_context_create($downloadOptions);
    $commitsInformationList = array();
    foreach ($repositoryNamesList as $name) {
        $rawCommitsInformation = file_get_contents(PROTOCOL_PREFIX . API_URL . '/' . REPOS . '/' . $settings . '/' . $name . '/' . COMMITS . '?' . $authentication, false, $streamContext);
        $commitsInformation = json_decode($rawCommitsInformation, true);
        $commitsInformationList[$name] = $commitsInformation;
    }

    $dates = array();
    foreach ($commitsInformationList as $key => $commits) {

        foreach ($commits as $commit) {
            $dates[$key][] = $commit['commit']['author']['date'];
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
    list($account, $authentication, $downloadOptions) = getSettings($setting);

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

