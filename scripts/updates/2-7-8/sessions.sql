DROP TABLE `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` ( id VARCHAR(128) PRIMARY KEY, data TEXT NOT NULL, last_accessed INT NOT NULL, INDEX (last_accessed) );
