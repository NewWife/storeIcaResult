<?php
ini_set('default_charset', 'UTF-8');
include('DataBaseHandler.php');

// URLloaderからの呼び出しの場合、
// echoなどの標準出力が結果的にActionScript3.0への出力処理になる？
/*
$twitterID = h($_GET['twitterID']);
$topicKeyword = h($_GET['topicKeyword']);
$lowerDate = h($_GET['lowerDate']);
$upperDate = h($_GET['upperDate']);
*/

// ファイルからデバック
$filePath = $argv[1];
$fp = fopen($filePath, 'r');
if($fp === FALSE)
{
	echo 'Failure Load File', PHP_EOL;
	exit(-1);
}
try
{
	// 一行のみの読み取り
	$line = rtrim(fgets($fp));
	// 一行を空白で分割し、結果を取得
	$columns = explode(',', $line);
	$twitterID = $columns[0];
	$topicKeyword = $columns[1];
	$lowerDate = $columns[2];
	$upperDate = $columns[3];
}
catch(FileException $e)
{
	var_dump($e->getMessage());
	exit(-1);
}


// データベースの扱いを簡易化したクラスを生成
$dbHandler = new DataBaseHandler();


if(isset($topicKeyword) && isset($lowerDate) && isset($upperDate))
{
	// 指定されたトピックと期間からの全てのユーザの
	// トピックに関する好評・不評・ツイート数の連想配列を取得する
	$usersTweet = $dbHandler->getUserTweetTable($topicKeyword, $lowerDate, $upperDate);
	// データの取り出し方
	echo $lowerDate .'から'. $upperDate . 'までの'. $topicKeyword . 'に関するユーザ毎の情報', PHP_EOL;
	foreach($usersTweet as $userTweet)
	{
		echo 'twitterID: ' . $userTweet['twitter_id'], PHP_EOL;		// TwitterID
		// 指定されたトピックと期間中に関する
		// 好評 + 不評 = ツイート数になるかな？
		echo '正の相関: ' . $userTweet['good'], PHP_EOL;				// 好評
		echo '負の相関: ' . $userTweet['bad'], PHP_EOL;				// 不評
		echo 'ツイート数: ' . $userTweet['number_of_tweet'], PHP_EOL;	// ツイート数
		echo PHP_EOL;
	}
}

if(isset($twitterID))
{
	// twitterIDからフォロワー数の取得
	$numberOfFollower = $dbHandler->getNumberOfFollower($twitterID);
	echo 'twitterID -> ' .$twitterID. ' のフォロワー数: '. $numberOfFollower, PHP_EOL;

	// twitterIDからフォローしているユーザIDの連想配列を取得
	$followInfos = $dbHandler->getFollowInformation($twitterID);
	// 指定したユーザがフォローしている全てのユーザの表示
	foreach($followInfos as $info)
	{
		echo $info['follow_twitter_id'], PHP_EOL;
	}
}

// htmlspecialcharsの短縮
function h($str){return htmlspecialchars($str);}