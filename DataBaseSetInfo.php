<?php
include('DataBaseHandler.php');

if($argc != 2)
{
	echo 'Must to input 2 arguments', PHP_EOL;
	exit(-1);
}

$path = $argv[1]; // ここに入力したいファイルのパスを入れる

$fp = fopen($path, 'r');

// データベースの扱いを簡易化したクラスを生成
$dbHandler = new DataBaseHandler();

try
{
	// 先頭から処理の内容を抜き取る
	$kind = rtrim(fgets($fp));
	echo 'Kind process type : '. $kind , PHP_EOL;
	// ファイルを一行ずつ読み込む
	while(feof($fp) === FALSE)
	{
		$line = rtrim(fgets($fp));
		// 一行を','で分割する
		$columns = explode(',', $line);
		// 処理に対応するファイル（自動、もしくは手作業で）を用意すれば大丈夫です。
		// ※ ファイル形式については各処理に記載
		switch($kind)
		{
		// Topicテーブルのデータを入力したい場合
		// 一行辺りのファイル形式：トピックのキーワード
		case 'topic':
			$topicKeyword = $columns[0];
			if($topicKeyword == '') continue; // 空文字列なら無視
			if($dbHandler->checkTopic($topicKeyword)) // 重複チェック
			{
				$dbHandler->setTopic($topicKeyword);
			}
			else
			{
				echo $topicKeyword. 'は既にTopicテーブルに存在します.', PHP_EOL;
			}
			break;
		// Timeテーブルのデータを入力したい場合
		// 一行辺りのファイル形式：トピックのキーワード,指定期間(現在は月単位)
		case 'date':
			$topicKeyword = $columns[0];
			$date = $columns[1];
			$topicID = $dbHandler->getTopicID($topicKeyword);
			if($topicKeyword == '' || $topicID == -1) continue; // 空文字列なら無視
			if($dbHandler->checkDate($topicID, $date)) // 重複チェック
			{
				$dbHandler->setDate($topicID, $date);
			}
			else
			{
				echo $topicKeyword.' & '.$date. 'は既にDateテーブルに存在します.', PHP_EOL;
			}
			break;
		// UserTweetテーブルのデータを入力したい場合
		// 一行辺りのファイル形式：トピックのキーワード,指定期間(現在は月単位),ツイートをしたユーザの固定twitterID,
		// ユーザのトピックに対する好評の累計値,ユーザに対する不評の累計値,期間中のトピックに関するツイート数
		case 'user_tweet':
			$topicKeyword = $columns[0];
			$date = $columns[1];
			$twitterID = $columns[2];
			$good = (int)$columns[3];
			$bad = (int)$columns[4];
			$numberOfTweet = (int)$columns[5];
			$dateID = $dbHandler->getDateID($date);
			if($dateID == -1) continue; // 無効なら入力なら無視
			if($dbHandler->checkUserTweet($twitterID, $dateID)) // 重複チェック
			{
				$dbHandler->setUserTweet($twitterID, $dateID, $good, $bad, $numberOfTweet);
			}
			else
			{
				echo $topicKeyword.' & '.$twitterID. 'は既にUserTweetテーブルに存在します.', PHP_EOL;
			}
			break;
		// Userテーブルのデータを入力したい場合
		// 一行辺りのファイル形式：ユーザの固定twitterID
		case 'user':
			$twitterID = $columns[0];
			$numberOfFollower = (int)$columns[1];
			if($dbHandler->checkUser($twitterID)) // 重複チェック
			{
				$dbHandler->setUser($twitterID, $numberOfFollower);
			}
			else
			{
				echo $twitterID. 'は既にUserテーブルに存在します.', PHP_EOL;
			}
			break;
		// Followテーブルのデータを入力したい場合
		// 一行辺りのファイル形式： ユーザの固定twitterID,そのユーザがフォローしている固定twitterID
		case 'follow':
			$twitterID = $columns[0];
			$followID = $columns[1];
			if($dbHandler->checkFollow($twitterID, $followID))
			{
				$dbHandler->setFollow($twitterID, $followID);
			}
			else
			{
				echo $twitterID. '&' .$followID. 'は既にUserテーブルに存在します.', PHP_EOL;
			}
			break;
		default:
			echo 'Failure switch process!', PHP_EOL;
		}
	}
	echo 'Complete your query',PHP_EOL;
}
catch(FileException $e)
{
	echo "File error: ".$e->getMessage();
	exit(-1);
}
// htmlspecialcharsの短縮
function h($str){return htmlspecialchars($str);}