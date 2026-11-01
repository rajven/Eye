user_auth.device_model_id is dropped
removed: user_auth.device_model_id - the addresses of a user are records of one device, the model is taken from the device bound to the address only
note: the sql patches contain no comments, upgrade.pl skips a statement which starts with a comment
