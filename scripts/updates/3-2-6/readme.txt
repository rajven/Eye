the api key of a customer is a secret of the installation, the database keeps a hash of it now
added: customers.api_key_hash - the hash of the api key of a customer, an api request is allowed by this hash alone
changed: customers.api_key is obsolete, it is not written any more and is cleared by the first request which comes with such a plain key
note: eye hashes a key with HMAC SHA256, the pepper of the hash is ENCRYPTION_KEY of html/cfg/config.php, the same secret which ciphers the passwords of the devices, changing it invalidates every key of every customer
note: the hash is found by an index, so a key is checked by computing its hash, and the hash of a leaked database can not be replayed as a key
note: an installation which still keeps a plain key in customers.api_key is migrated by the first successful request with that key, no data patch is needed
note: the sql patches contain no comments, upgrade.pl skips a statement which starts with a comment
