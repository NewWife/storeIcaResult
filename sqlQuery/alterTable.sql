-- topic
ALTER TABLE `topic` ADD INDEX index_keyword (`keyword`);
-- date
ALTER TABLE `date` ADD INDEX index_topic_id (`topic_id`);
ALTER TABLE `date` ADD INDEX index_date (`date.date`);
-- user_tweet
ALTER TABLE `user_tweet` ADD INDEX index_twitter_id (`twitter_id`);
ALTER TABLE `user_tweet` ADD INDEX index_date_id (`date_id`);
-- follow
ALTER TABLE `follow` ADD INDEX index_twitter_id (`twitter_id`);
ALTER TABLE `follow` ADD INDEX index_follow_twitter_id (`follow_twitter_id`);