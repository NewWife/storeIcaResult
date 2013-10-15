<?php
date_default_timezone_set('Asia/Tokyo');
ini_set('default_charset', 'UTF-8');
require_once("config.inc.php");
// DBTYPE, DBNAME, DBHOST, DBUSER, DBPASS, DSN
//データベースに関する情報を管理するクラス
class DataBaseHandler
{
	private $pdo; // PDOを保持する変数
	
	public function __construct()
	{
		// MySQLが使用可能かどうかのチェック
		$existDriver = false;
		$drivers = PDO::getAvailableDrivers();
		foreach($drivers as $driver)
		{
			if('mysql' === $driver)
			{
				$existDriver = true;
			}
		}
		if($existDriver === FALSE)
		{
			echo 'MySQL driver not Exist', PHP_EOL;
			exit;
		}
		try
		{
			$this->pdo = new PDO(DSN, DBUSER, DBPASS, array( PDO::ATTR_EMULATE_PREPARES => false ));
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		}
		catch(PDOExeception $e)
		{
			var_dump($e->getMessage());
			exit;
		}
		
		//echo 'Success DB Access', PHP_EOL;
	}
	
	public function __destruct()
	{
		unset($this->pdo);
	}
	
	//* ---------- * //
	//*   Getter   * //
	//* ---------- * // 
	// 日付の形式は、YYYY/MM/DD
	// 指定されたトピックに関する期間中のユーザテーブルを取得する
	public function getUserTweetTable($topicKeyword, $dateLower, $dateUpper)
	{
		// トピック（キーワード）のテーブルを取得する
		$sql = <<< EOM
			SELECT * FROM topic
			WHERE :topic_keyword = topic.keyword;
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_keyword', $topicKeyword, PDO::PARAM_STR);
		$statement->execute();
		//$statement->execute(array(':topic_keyword' => $topicKeyword));
		
		$topicID = null;
		// トピックのテーブルが無かった場合
		if($statement->rowCount() == 0)
		{
			// Topicテーブルに新しく追加する
			//echo 'Insert INTO Topic ' . $topicKeyword . PHP_EOL;
			$this->setTopic($topicKeyword);
		}
		$topicID = $this->getTopicID($topicKeyword);

		// 月の差に変換
		$ms = date('Y', strtotime($dateLower))*12 + date('m', strtotime($dateLower));
		$me = date('Y', strtotime($dateUpper))*12 + date('m', strtotime($dateUpper));
		$diff = $me - $ms;
		// Timeテーブルに新しく追加する
		for($i = 0; $i <= $diff; ++$i)
		{
			$date = date('Y/m/d', strtotime("$dateLower + $i month"));
			// DEBUG
			//echo 'Now process date : ' . $date . PHP_EOL;
			// 対応する日付の情報がない場合
			$newDateInfo = false;
			if($this->checkDate($topicID, $date) === TRUE)
			{
				// DEBUG
				//echo 'Insert INTO Date ' . $date . PHP_EOL;
				// Dateテーブルに追加する
				$this->setDate($topicID, $date);
				$newDateInfo = true;
			}
			//echo 'Topic ID : ' . $topicID . PHP_EOL;
			$dateID = $this->getDateID($topicID, $date);
			// DEBUG
			//echo 'Date ID : ' , $dateID , PHP_EOL;

			$start_time = date('Y-m-01', strtotime("$dateLower + $i month"));
			$end_time = date('Y-m-t', strtotime("$dateLower + $i month"));

			// トピック（キーワード）の指定された期間のユーザの呟きの情報を結合する
			$sql = <<< EOM
				SELECT user_tweet.twitter_id, user_tweet.good, user_tweet.bad, user_tweet.number_of_tweet
				FROM topic
				INNER JOIN date
					ON topic.topic_id = date.topic_id
				INNER JOIN user_tweet
					ON date.date_id = user_tweet.date_id
				WHERE
					:topic_keyword = topic.keyword AND
					STR_TO_DATE(:date_lower, '%Y-%m-%d') <= date.date AND
					date.date <= STR_TO_DATE(:date_upper, '%Y-%m-%d');
EOM;

			$statement = $this->pdo->prepare($sql);
			$statement->bindValue(':topic_keyword', $topicKeyword, PDO::PARAM_STR);
			$statement->bindValue(':date_lower', $start_time, PDO::PARAM_STR);
			$statement->bindValue(':date_upper', $end_time, PDO::PARAM_STR);
			$statement->execute();
			//$statement->execute(array(':topic_keyword' => $topicKeyword, ':date_lower' => $dateLower, ':date_upper' => $dateUpper));

			//echo 'Topic ID : ' . $topicID . PHP_EOL;
			//echo 'Start Time : ' , $start_time, PHP_EOL, 'End Time : ' , $end_time , PHP_EOL;
			//var_dump($statement->fetchAll());
			// 一致するユーザの呟き情報が無かった場合
			if($statement->rowCount() == 0 && $newDateInfo === TRUE)
			{
				// DEBUG
				// echo 'Start Time : ' , $start_time, PHP_EOL, 'End Time : ' , $end_time , PHP_EOL;
				// ICAから一ヶ月単位で取得
				$icaResults = $this->ica_access($topicKeyword, $start_time, $end_time);

				// @param $results['posi']['negative'], ['weight'], ["correlation"]
				// 取得した結果をDBに格納する
				//var_dump($icaResults);
				//die;
				if(count($icaResults) === 0) continue;
				foreach($icaResults as $icaResult)
				{
					$twitterID = (string)$icaResult['id'];
					$good = (double)$icaResult['good'];
					$bad = (double)$icaResult['bad'];
					$numberOfTweet = $icaResult['number_of_tweet'];
					// echo 'date ID : ' , $dateID , PHP_EOL;
					$result = $this->setUserTweet($twitterID, $dateID, $good, $bad, $numberOfTweet);
					if($result === FALSE)
					{
						echo 'Failure Insert user_tweet', PHP_EOL;
					}
				}
			}
		}
		
		// トピック（キーワード）の指定された期間のユーザの呟きの情報を結合する
		$sql = <<< EOM
			SELECT user_tweet.twitter_id, SUM(user_tweet.good) AS good, SUM(user_tweet.bad) AS bad, SUM(user_tweet.number_of_tweet) AS number_of_tweet
			FROM topic
			INNER JOIN date
				ON topic.topic_id = date.topic_id
			INNER JOIN user_tweet
				ON date.date_id = user_tweet.date_id
			WHERE
				:topic_keyword = topic.keyword AND
				STR_TO_DATE(:date_lower, '%Y/%m/%d') <= date.date AND
				date.date <= STR_TO_DATE(:date_upper, '%Y/%m/%d')
			GROUP BY user_tweet.twitter_id;
EOM;

		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_keyword', $topicKeyword, PDO::PARAM_STR);
		$statement->bindValue(':date_lower', $dateLower, PDO::PARAM_STR);
		$statement->bindValue(':date_upper', $dateUpper, PDO::PARAM_STR);
		$statement->execute();
		//$statement->execute(array(':topic_keyword' => $topicKeyword, ':date_lower' => $dateLower, ':date_upper' => $dateUpper));

		return $statement->fetchAll();
	}
	
	// ユーザのフォロワー数を取得
	public function getNumberOfFollower($twitterID)
	{
		$sql = <<< EOM
			SELECT number_of_follower
			FROM user WHERE :twitter_id = user.twitter_id
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->execute();
		// 一つしかフォロワー数が取得されないはずなので、その結果を返す
		$row = $statement->fetch();
		return $row['number_of_follower'];
	}
	
	// ユーザのフォローしているID郡を取得する
	public function getFollowInformation($twitterID)
	{
		$sql = <<< EOM
			SELECT follow.follow_twitter_id
			FROM user INNER JOIN follow ON user.twitter_id = follow.twitter_id
			WHERE :twitter_id = user.twitter_id
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->execute();
		return $statement->fetchAll();
	}
	
	// トピックキーワードからtopic_idを取得する
	public function getTopicID($topicKeyword)
	{
		$sql = <<< EOM
			SELECT * FROM topic
			WHERE :topic_keyword = keyword;
EOM;
		try
		{
			$statement = $this->pdo->prepare($sql);
			$statement->bindValue(':topic_keyword', $topicKeyword, PDO::PARAM_STR);
			$statement->execute();
			$row = $statement->fetch(PDO::FETCH_ASSOC);
		}
		catch(PDOException $e)
		{
			exit($e->getMessage());
		}
		// SQLクエリの結果取得失敗
		if($row === FALSE) return -1;
		return (int)$row['topic_id'];
	}
	
	// トピックキーワードと日付から対応するdate_idを取得する
	public function getDateID($topicID, $date)
	{
		$sql = <<< EOM
			SELECT * FROM date
			WHERE
				topic_id = :topic_id AND
				date = STR_TO_DATE(:date, '%Y/%m/%d');
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_id', $topicID, PDO::PARAM_INT);
		$statement->bindValue(':date', $date, PDO::PARAM_STR);
		$statement->execute();
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		if($row === FALSE) return -1;
		return (int)$row['date_id'];
	}
	
	
	//* ---------- * //
	//*   Setter   * //
	//* ---------- * // 
	// Topicのテーブルに情報を追加する
	public function setTopic($topicKeyword)
	{
		$sql = <<< EOM
			INSERT INTO topic (keyword, created)
			VALUES (:topic_keyword, NOW())
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_keyword', $topicKeyword, PDO::PARAM_STR);
		$statement->execute();
	}
	
	// Dateのテーブルに情報を追加する
	public function setDate($topicID, $date)
	{
		$sql = <<< EOM
			INSERT INTO date (topic_id, date, created)
			VALUES (:topic_id, STR_TO_DATE(:date, '%Y/%m/%d'), NOW())
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_id', $topicID, PDO::PARAM_INT);
		$statement->bindValue(':date', $date, PDO::PARAM_STR);
		$statement->execute();
	}
	
	// UserTweetのテーブルに情報を追加する
	public function setUserTweet($twitterID, $dateID, $good, $bad, $numberOfTweet)
	{
		$sql = <<< EOM
			INSERT INTO user_tweet (twitter_id, date_id, good, bad, number_of_tweet, created)
			VALUES (:twitter_id, :date_id, :good, :bad, :number_of_tweet, NOW())
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->bindValue(':date_id', $dateID, PDO::PARAM_INT);
		$statement->bindValue(':good', $good, PDO::PARAM_INT);
		$statement->bindValue(':bad', $bad, PDO::PARAM_INT);
		$statement->bindValue(':number_of_tweet', $numberOfTweet, PDO::PARAM_INT);
		return $statement->execute();
	}
	
	// Userのテーブルに情報を追加する
	public function setUser($twitterID, $numberOfFollower)
	{
		$sql = <<< EOM
			INSERT INTO user (twitter_id, number_of_follower, created)
			VALUES (:twitter_id, :number_of_follower, NOW())
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->bindValue(':number_of_follower', $numberOfFollower, PDO::PARAM_INT);
		$statement->execute();
	}
	
	// Followのテーブルに情報を追加する
	public function setFollow($twitterID, $followID)
	{
		$sql = <<< EOM
			INSERT INTO follow (twitter_id, follow_twitter_id, created)
			VALUES (:twitter_id, :follow_twitter_id, NOW())
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->bindValue(':follow_twitter_id', $followID, PDO::PARAM_STR);
		$statement->execute();
	}
	
	//* ---------- * //
	//*   Checker  * //
	//* ---------- * // 
	// 指定されたキーワードがTopicのテーブルに存在するか
	public function checkTopic($topicKeyword)
	{
		$sql = <<< EOM
			SELECT * FROM topic
			WHERE :topic_keyword = keyword;
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_keyword', $topicKeyword, PDO::PARAM_STR);
		if($statement->execute() === FALSE)
		{
			exit('正常に'.$sql.'が実行できませんでした\n');
		}
		return ($statement->rowCount() === 0);
	}
	
	// 指定されたキーワードと日付が同じ物がDateのテーブルに存在するか
	// これだけ名前が違うのは同名の関数があることが判明したから
	public function checkDate($topicID, $date)
	{
		$sql = <<< EOM
			SELECT * FROM date
			WHERE :topic_id = topic_id AND STR_TO_DATE(:date, '%Y/%m/%d') = date.date;
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':topic_id', $topicID, PDO::PARAM_INT);
		$statement->bindValue(':date', $date, PDO::PARAM_STR);
		$statement->execute();
		return ($statement->rowCount() === 0);
	}
	
	// 指定されたツイッターIDと日付IDがuser_tweetテーブルに存在するか
	public function checkUserTweet($twitterID, $dateID)
	{
		$sql = <<< EOM
			SELECT * FROM user_tweet
			WHERE :twitter_id = twitter_id AND :date_id = date_id;
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->bindValue(':date_id', $dateID, PDO::PARAM_INT);
		$statement->execute();
		return ($statement->rowCount() === 0);
	}
	
	// 指定されたツイッターIDとuserテーブルに存在するか
	public function checkUser($twitterID)
	{
		$sql = <<< EOM
			SELECT * FROM user
			WHERE :twitter_id = twitter_id
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->execute();
		return ($statement->rowCount() === 0);
	}
	
	// 指定されたツイッターIDとフォローするツイッターIDがfollowテーブルに存在するか
	public function checkFollow($twitterID, $followID)
	{
		$sql = <<< EOM
			SELECT * FROM follow
			WHERE :twitter_id = twitter_id AND :follow_twitter_id = follow_twitter_id;
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->bindValue(':follow_twitter_id', $followID, PDO::PARAM_STR);
		$statement->execute();
		return ($statement->rowCount() === 0);
	}

	//* ---------- * //
	//*   Update   * //
	//* ---------- * // 
	public function updateUserFollower($twitterID, $numberOfFollower)
	{
		$sql = <<< EOM
			UPDATE user
			SET number_of_follower = :number_of_follower
			WHERE  twitter_id = :twitter_id;
EOM;
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(':twitter_id', $twitterID, PDO::PARAM_STR);
		$statement->bindValue(':number_of_follower', $numberOfFollower, PDO::PARAM_INT);
		$statement->execute();
	}


	//* ---------- * //
	//*     ICA    * //
	//* ---------- * // 
	// Written by Ogata
	private function ica_access($word, $start_time, $end_time)
	{
		$ret = array('positive' => array(), 'negative' => array());
		$word_e = urlencode($word);
		$reputation=array("positive", "negative");
		//一回目ポジティブ　2回目ネガティブ
		for($i=0; $i<2; $i++){
			
		//	$url1="http://iplcr02.u-aizu.ac.jp:8393/api/v10/search/facet?collection=col_57929&facet={%22namespace%22:%22keyword%22,%22id%22:%22$.id%22,%22count%22:%2210000%22}&query=keyword::/%22Part%20of%20Speech%22/%22Noun%22/%22General%20Noun%22/%22%E3%83%93%E3%83%BC%E3%83%AB%22%20IN%20_negative%20AND%20(%20date%3E=%222012-06-01%22%20date%3C=%222012-06-30%22%20)";
			$url2="http://iplcr02.u-aizu.ac.jp:8393/api/v10/search/facet?collection=col_57929&facet={%22namespace%22:%22keyword%22,%22id%22:%22$.id%22,%22count%22:%2210000%22}&query=keyword::/%22Part%20of%20Speech%22/%22Noun%22/%22General%20Noun%22/%22". $word_e ."%22%20IN%20_". $reputation[$i] ."%20AND%20(%20date%3E=%22". $start_time ."%22%20date%3C=%22". $end_time."%22%20)";
			
			$string = file_get_contents($url2);
			$string = preg_replace('/:/', '_', $string);
			$xml = simplexml_load_string($string);
			//var_dump($xml);

			$results = $xml->ibmsc_facet->ibmsc_facetValue;

			//var_dump($results);

			$ica_array = array();
			
			foreach($results as $result)
			{
				$temps["id"] = (string)$result->attributes()->label;
				$temps["weight"] = (int)$result->attributes()->weight;
				$temps["correlation"] = (double)$result->es_property->attributes()->value;
				// 現状では相関の値の足し合わせになっているのを、ツイート数に変更する
				//$temps["correlation"] = (int)$result->attributes()->weight;
				array_push($ica_array, $temps);
			}
			$ret[$reputation[$i]] = $ica_array;
//echo "xml取得完了\n";
		}
		//return $ret;
		$result_array = array();
		$flg = array();

		foreach($ret["positive"] as $result){
			//echo $result["id"] . "\n";
			$temps2["id"] = $result["id"];
			$temps2["good"] = $result["correlation"];
			$temps2["bad"] = 0.0;
			$temps2["number_of_tweet"] = $result["weight"];
			//$flg[$result["id"]] = true;
			array_push($result_array, $temps2);
		}

		foreach($ret["negative"] as $result){

			$flag = FALSE;
			//if(empty($flg[$result['id']])) continue;
			foreach($result_array as &$check_result){
				//var_dump($check_result);
//				die;
				if($result["id"]==$check_result["id"]){
					//一致した場合
					//var_dump($result);
					/*
					echo "1:" . $check_result["bad"] . "\n";
					echo "2:" . $result["correlation"] . "\n";
					echo "3:" . $check_result["number_of_tweet"] ."\n";
					echo "4:" . $result["weight"] ."\n";
					*/
					$check_result["bad"] = $result["correlation"];
					$check_result["number_of_tweet"] += $result["weight"];
					$flag = TRUE;
//					echo "----------------------------------------------------------------一致した\n";
					break;
				}
			}
			if($flag == FALSE){
				$temps3["id"] = $result["id"];
				$temps3["good"] = 0.0;
				$temps3["bad"] = $result["correlation"];
				$temps3["number_of_tweet"] = $result["weight"];
				array_push($result_array, $temps3);
			}

		}
		//var_dump($result_array);
		return $result_array;
	}
}
?>