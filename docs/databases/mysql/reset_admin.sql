--- default user: admin
--- default password admin
INSERT INTO `Customers` (`id`, `Login`, `password`, `api_key`,`rights`) VALUES (1, 'admin', '$2y$11$wohV8Tuqu0Yai9Shacei5OKfMxG5bnLxB5ACcZcJJ3pYEbIH0qLGG', 'c3284d0f94606de1fd2af172aba15bf31','1') ON DUPLICATE KEY UPDATE Login="admin", password="$2y$11$wohV8Tuqu0Yai9Shacei5OKfMxG5bnLxB5ACcZcJJ3pYEbIH0qLGG";
