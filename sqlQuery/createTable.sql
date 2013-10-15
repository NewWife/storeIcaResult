USE creative_craft;
CREATE TABLE IF NOT EXISTS user
(
	twitter_id VARCHAR(255) NOT NULL,
	number_of_follower INT(11), -- フォロワー数
	created DATETIME,
	modified TIMESTAMP,
	PRIMARY KEY (twitter_id)
);
CREATE TABLE IF NOT EXISTS follow
(
	follow_id INT(11) NOT NULL AUTO_INCREMENT,
	twitter_id VARCHAR(255) NOT NULL,
	follow_twitter_id VARCHAR(255) NOT NULL,
	created DATETIME,
	modified TIMESTAMP,
	PRIMARY KEY (follow_id),
	INDEX index_twitter_id (`twitter_id`),
	INDEX index_follow_twitter_id (`follow_twitter_id`)
);
	-- FOREIGN KEY (twitter_id)
	-- REFERENCES user(twitter_id)

-- 通し番号でなく主キーをkeywordに変更可能.
CREATE TABLE IF NOT EXISTS topic
(
	topic_id INT(11) NOT NULL AUTO_INCREMENT,
	keyword VARCHAR(255),
	created DATETIME,
	modified TIMESTAMP,
	PRIMARY KEY (topic_id),
	INDEX index_keyword (`keyword`)
);
-- 通し番号でなく主キーをdateに変更可能.
CREATE TABLE IF NOT EXISTS date
(
	date_id INT(11) NOT NULL AUTO_INCREMENT,
	topic_id INT(11) NOT NULL,
	date DATE,
	created DATETIME,
	modified TIMESTAMP,
	PRIMARY KEY (date_id),
	FOREIGN KEY (topic_id)
		REFERENCES topic(topic_id),
	INDEX index_topic_id (`topic_id`),
	INDEX index_date (`date.date`)
);
CREATE TABLE IF NOT EXISTS user_tweet
(
	user_tweet_id INT(11) NOT NULL AUTO_INCREMENT,
	twitter_id VARCHAR(255) NOT NULL,
	date_id INT(11) NOT NULL,
	good DOUBLE,
	bad DOUBLE,
	number_of_tweet INT(11),
	created DATETIME,
	modified TIMESTAMP,
	PRIMARY KEY (user_tweet_id),
	FOREIGN KEY (date_id)
		REFERENCES date(date_id),
	FOREIGN KEY (twitter_id)
		REFERENCES user(twitter_id),
	INDEX index_twitter_id (`twitter_id`),
	INDEX index_date_id (`date_id`)
);
