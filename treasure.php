<?php

header('Content-Type: text/html; charset=UTF-8');

$path = __DIR__ . DIRECTORY_SEPARATOR;
$game = isset($_REQUEST['game']) ? (string) $_REQUEST['game'] : 'jer_heb';
if ($game === '') {
    $game = 'jer_heb';
}
$team = isset($_REQUEST['team']) ? (string) $_REQUEST['team'] : '';
$conf = loadConfig($path . 'data' . DIRECTORY_SEPARATOR . $game . '.conf');
$now = date('Ymd_His');
$currentPoints = 0;
$elapsedTime = 0;

if ($team === '') {
    $output = translateHtml(readFileContents($path . 'html' . DIRECTORY_SEPARATOR . 'first.htm'));
    outputHtml($output);
    exit;
}

if ($team === 'admin') {
    showAdmin($path, $game, $conf);
    exit;
}

$teamFile = $path . 'data' . DIRECTORY_SEPARATOR . 'team_' . $game . '_' . $team . '.data';

if (isset($_REQUEST['showMap'])) {
    showMap($teamFile, $path);
}

if (isset($_REQUEST['showLog'])) {
    showLog($teamFile);
}

$pageParam = isset($_REQUEST['page']) ? (string) $_REQUEST['page'] : '';
$pageNo = substr($pageParam, 1, 2);
$question = isset($conf[$pageNo . '_q']) ? $conf[$pageNo . '_q'] : '';
$answer = isset($_REQUEST['answer']) ? (string) $_REQUEST['answer'] : '';
$attempt = isset($_REQUEST['attempt']) ? (string) $_REQUEST['attempt'] : '';

if ($pageNo === '01' && $answer === '' && file_exists($teamFile)) {
    echo "Team name already taken. Please pick a new name\n";
    exit;
}

$teamText = file_exists($teamFile) ? readFileContents($teamFile) : '';
$teamHandle = fopen($teamFile, 'ab');
if ($teamHandle === false) {
    http_response_code(500);
    exit('Cannot open team file for output');
}

if (strpos($teamText, 'start timestamp') === false) {
    fwrite($teamHandle, 'start timestamp:' . time() . "\ntime:" . $now . "\n");
}

if (preg_match('/.*points:(\d*)/s', $teamText, $matches)) {
    $currentPoints = (int) $matches[1];
}
$elapsedTime = calcElapsed($teamText, $conf);

if ($attempt === '' || $attempt === '0') {
    $attempt = '1';
}

if ($pageParam !== '' && $answer === '') {
    if (isset($conf[$pageNo . '_q_img']) && $conf[$pageNo . '_q_img'] !== '') {
        $question .= '<br/><img style="width:100%; max-width:800px" src="' . $conf[$pageNo . '_q_img'] . '"./>';
    }
    $output = translateHtml(readFileContents($path . 'html' . DIRECTORY_SEPARATOR . 'question.htm'));
    $output = str_replace(
        array('{page}', '{attempt}', '{question}'),
        array($pageParam, $attempt, $question),
        $output
    );
    outputHtml($output);
    fwrite($teamHandle, 'page: ' . $pageNo . ' attempt:' . $attempt . "\n");
    fclose($teamHandle);
    exit;
}

if ($pageParam !== '' && $answer !== '') {
    if (isset($_REQUEST['lat']) && (string) $_REQUEST['lat'] !== '') {
        $longitude = isset($_REQUEST['lon']) ? $_REQUEST['lon'] : '';
        fwrite($teamHandle, 'GEO: lat ' . $_REQUEST['lat'] . ' lon ' . $longitude . "\n");
        fwrite($teamHandle, 'log time: ' . $now . "\n");
    }

    $correctAnswer = isset($conf[$pageNo . '_a']) ? $conf[$pageNo . '_a'] : '';
    echo '<!-- ooo ' . $pageNo . ' ' . $correctAnswer . ' -->';
    $addedPoints = 12 - (2 * (int) $attempt);

    if (preg_match('/' . $correctAnswer . '/iu', $answer) === 1) {
        $currentPoints += $addedPoints;
        $output = translateHtml(readFileContents($path . 'html' . DIRECTORY_SEPARATOR . 'correct.htm'));
        $output = str_replace(
            array('{page}', '{added_points}', '{after_reply}'),
            array(hiddenPageNum((int) $pageNo + 1), $addedPoints, isset($conf[$pageNo . '_after_reply']) ? $conf[$pageNo . '_after_reply'] : ''),
            $output
        );
        outputHtml($output);
        fwrite($teamHandle, 'CORRECT page: ' . $pageNo . ' attempt:' . $attempt . ' correct_answer:' . $correctAnswer . ' . answer:' . $answer . ' elapsed:' . $elapsedTime . "\n");
        fwrite($teamHandle, 'points:' . $currentPoints . "\n");
        fwrite($teamHandle, 'timestamp page: ' . $pageNo . ' timestamp now:' . time() . "\n");
        fclose($teamHandle);
        exit;
    }

    fwrite($teamHandle, 'WRONG page: ' . $pageNo . ' attempt:' . $attempt . ' correct_answer:' . $correctAnswer . ' . answer:' . $answer . ' elapsed:' . $elapsedTime . "\n");
    $attempt++;

    if ($attempt > 3) {
        $output = translateHtml(readFileContents($path . 'html' . DIRECTORY_SEPARATOR . 'after_many_mistakes.htm'));
        $output = str_replace(
            array('{page}', '{after_reply}', '{answer}'),
            array(hiddenPageNum((int) $pageNo + 1), isset($conf[$pageNo . '_after_reply']) ? $conf[$pageNo . '_after_reply'] : '', $correctAnswer),
            $output
        );
        outputHtml($output);
        fwrite($teamHandle, 'MANY_WRONG page: ' . $pageNo . ' attempt:' . $attempt . ' correct_answer:' . $correctAnswer . ' . answer:' . $answer . ' elapsed:' . $elapsedTime . "\n");
        fwrite($teamHandle, 'points:' . $currentPoints . "\n");
        fwrite($teamHandle, 'timestamp page: ' . $pageNo . ' timestamp now:' . time() . "\n");
        fclose($teamHandle);
        exit;
    }

    if (isset($conf[$pageNo . '_q_img']) && $conf[$pageNo . '_q_img'] !== '') {
        $question .= '<br/><img style="width:100%; max-width:800px;" src="' . $conf[$pageNo . '_q_img'] . '"./>';
    }
    $output = translateHtml(readFileContents($path . 'html' . DIRECTORY_SEPARATOR . 'wrong.htm'));
    $output = str_replace(
        array('{page}', '{attempt}', '{question}'),
        array($pageParam, $attempt, $question),
        $output
    );
    outputHtml($output);
    fclose($teamHandle);
    exit;
}

fclose($teamHandle);
$logFile = $path . 'logs' . DIRECTORY_SEPARATOR . 'main.log';
$logDirectory = dirname($logFile);
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0775, true);
}
file_put_contents($logFile, 'ooo ' . $now . "\n\n", FILE_APPEND);
echo "error...\n";

function loadConfig($file)
{
    if (!file_exists($file)) {
        return array();
    }

    $text = readFileContents($file);
    preg_match_all('/^([^:\r\n]+):\r?\n(.*?)(?=^[^:\r\n]+:\r?\n|\z)/ms', $text, $matches, PREG_SET_ORDER);
    $config = array();
    foreach ($matches as $match) {
        $config[$match[1]] = rtrim($match[2], "\r\n");
    }
    return $config;
}

function translateHtml($html)
{
    global $conf, $game, $team, $currentPoints, $elapsedTime;
    $values = $conf;
    $values['game'] = $game;
    $values['team'] = $team;
    $values['current_points'] = $currentPoints;
    $values['elapsed_time'] = $elapsedTime;
    foreach ($values as $key => $value) {
        $html = str_replace('{' . $key . '}', $value, $html);
    }
    return str_replace('treasure.pl', 'treasure.php', $html);
}

function outputHtml($html)
{
    echo $html;
}

function hiddenPageNum($number)
{
    return '7' . sprintf('%02d', $number) . '3432';
}

function calcElapsed($teamText, $config)
{
    $startTime = time() - 1;
    if (preg_match('/start timestamp:(\d*)/s', $teamText, $matches)) {
        $startTime = (int) $matches[1];
    }

    $timeToReduce = 0;
    if (isset($config['possible_pause']) && $config['possible_pause'] !== '') {
        $pausePage = $config['possible_pause'];
        $afterPausePage = (int) $pausePage + 1;
        $beforeTimestamp = 0;
        $afterTimestamp = 0;
        if (preg_match('/.*timestamp page: ' . preg_quote($pausePage, '/') . ' timestamp now:(\d*)/s', $teamText, $matches)) {
            $beforeTimestamp = (int) $matches[1];
        }
        if (preg_match('/.*timestamp page: ' . $afterPausePage . ' timestamp now:(\d*)/s', $teamText, $matches)) {
            $afterTimestamp = (int) $matches[1];
        }
        if ($beforeTimestamp > 0 && $afterTimestamp > 0) {
            $timeToReduce = $afterTimestamp - $beforeTimestamp;
        }
    }

    if (preg_match('/.*timestamp page: (\d*) timestamp now:(\d*)/s', $teamText, $matches)) {
        return wdhms((int) $matches[2] - $startTime - $timeToReduce);
    }
    return 0;
}

function wdhms($seconds)
{
    $sign = $seconds < 0 ? '-' : '';
    $seconds = abs($seconds);
    $minutes = (int) floor($seconds / 60);
    $seconds %= 60;
    $hours = (int) floor($minutes / 60);
    $minutes %= 60;
    $days = (int) floor($hours / 24);
    $hours %= 24;
    $weeks = (int) floor($days / 7);
    $days %= 7;

    $result = sprintf('%02d', $seconds);
    if ($minutes || $hours || $days || $weeks) {
        $result = sprintf('%02d:', $minutes) . $result;
    }
    if ($hours || $days || $weeks) {
        $result = sprintf('%02d:', $hours) . $result;
    }
    if ($days > 0) {
        $result = $days . 'd ' . $result;
        if ($weeks > 0) {
            $result = $weeks . 'w' . $result;
        }
    }
    return $sign . $result;
}

function readFileContents($file)
{
    $contents = @file_get_contents($file);
    if ($contents === false) {
        http_response_code(404);
        exit('Cannot read ' . $file);
    }
    return $contents;
}

function showAdmin($path, $game, $config)
{
    echo '<table border=1>';
    foreach (glob($path . 'data' . DIRECTORY_SEPARATOR . 'team*') as $file) {
        $theTeam = preg_replace('/.*_/', '', $file);
        $theTeam = preg_replace('/\.data$/', '', $theTeam);
        $teamText = readFileContents($file);
        echo '<tr><td><a href="treasure.php?game=' . $game . '&team=' . $theTeam . '&showMap=true">' . $theTeam . '</a></td>';
        echo '<td><a href="treasure.php?game=' . $game . '&team=' . $theTeam . '&showLog=true">log</a></td>';
        echo '<td>elapsed until last timestamp:' . calcElapsed($teamText, $config) . '</td></tr>';
    }
    echo '</table>';
}

function showMap($file, $path)
{
    $teamText = readFileContents($file);
    $values = '';
    if (preg_match_all('/GEO: lat (.*?) lon (.*?)\n/', $teamText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $values .= ' [' . $match[1] . ', ' . $match[2] . ', ""],';
        }
    }
    echo str_replace('{values}', $values, readFileContents($path . 'html' . DIRECTORY_SEPARATOR . 'showMap.htm'));
    exit;
}

function showLog($file)
{
    echo $file . " <br/>\n <pre>" . readFileContents($file) . "</pre>";
    echo "___________________<br/><br/><br/><br/>\n";
    exit;
}
