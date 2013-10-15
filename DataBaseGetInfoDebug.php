<?php
ini_set('default_charset', 'UTF-8');
include('DataBaseHandler.php');

// URLloaderからの呼び出しの場合

/*
$topicKeyword = h($_GET['category']);
$lowerDate = h($_GET['start']);
$upperDate = h($_GET['end']);
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
	// ファイルを一行ずつ読み込む
	while(feof($fp) === FALSE)
	{
		// 一行のみの読み取り
		$line = rtrim(fgets($fp));
		if($line == '') continue; // 空文字列なら無視
		// 一行を空白で分割し、結果を取得
		$columns = explode(',', $line);
		$topicKeyword = $columns[0];
		$lowerDate = $columns[1];
		$upperDate = $columns[2];

		// データベースの扱いを簡易化したクラスを生成
		$dbHandler = new DataBaseHandler();

		// 全てのリクエストのパラメータが指定されているか
		if(isset($topicKeyword) && isset($lowerDate) && isset($upperDate))
		{
			// 指定されたトピックと期間からの全てのユーザの
			// トピックに関する好評・不評・ツイート数の連想配列を取得する
			$usersTweet = $dbHandler->getUserTweetTable($topicKeyword, $lowerDate, $upperDate);
			// 全てのユーザからフォロー関係を取り出す
			foreach($usersTweet as $userTweet)
			{
				// twitterIDからフォロワー数の取得
				$numberOfFollower = $dbHandler->getNumberOfFollower($userTweet['twitter_id']);
				// twitterIDからフォローしているユーザIDの連想配列を取得
				$followInfos = $dbHandler->getFollowInformation($userTweet['twitter_id']);
				$followIDs = array();
				// フォローしているユーザを配列に格納する
				foreach($followInfos as $info)
				{
					array_push($followIDs, $info['follow_twitter_id']);
				}
				// 結果をビュワーの形式にして吐き出す
				/**
				* id:negative:positive:tweet_number:follower_number:
				* [id1,id2,id3,id4,...,idn]\n
				*/
				$result = $userTweet['twitter_id'] .':'. $userTweet['bad'] . ':'
					. $userTweet['good'] . ':' . $userTweet['number_of_tweet'] . ':' . $numberOfFollower . ':[';
				for ($i = 0, $n = count($followIDs); $i < $n; ++$i)
				{
					if($i != 0) $result .= ',';
					$result .= $followIDs[$i];
				}
				$result .= ']';
				echo $result, PHP_EOL;
			}
			echo count($usersTweet), PHP_EOL;
		}
	}
}
catch(FileException $e)
{
	var_dump($e->getMessage());
	exit(-1);
}

// htmlspecialcharsの短縮
function h($str){return htmlspecialchars($str);}