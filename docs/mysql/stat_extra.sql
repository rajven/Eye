
--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_rules`
--
ALTER TABLE `auth_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `building`
--
ALTER TABLE `building`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_options`
--
ALTER TABLE `config_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `connections`
--
ALTER TABLE `connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`),
  ADD KEY `device_id` (`device_id`,`port_id`);

--
-- Indexes for table `Customers`
--
ALTER TABLE `Customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `device_l3_interfaces`
--
ALTER TABLE `device_l3_interfaces`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `device_models`
--
ALTER TABLE `device_models`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `device_ports`
--
ALTER TABLE `device_ports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `port` (`port`),
  ADD KEY `target_port_id` (`target_port_id`);

--
-- Indexes for table `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dhcp_log`
--
ALTER TABLE `dhcp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`action`) USING BTREE;

--
-- Indexes for table `dns_cache`
--
ALTER TABLE `dns_cache`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Filter_list`
--
ALTER TABLE `Filter_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Group_filters`
--
ALTER TABLE `Group_filters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Group_list`
--
ALTER TABLE `Group_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mac_history`
--
ALTER TABLE `mac_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`mac`),
  ADD KEY `timestamp_2` (`timestamp`,`ip`);

--
-- Indexes for table `mac_vendors`
--
ALTER TABLE `mac_vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oui` (`oui`);

--
-- Indexes for table `OU`
--
ALTER TABLE `OU`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Queue_list`
--
ALTER TABLE `Queue_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `remote_syslog`
--
ALTER TABLE `remote_syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date_2` (`date`),
  ADD KEY `date` (`date`,`device_id`,`ip`);
ALTER TABLE `remote_syslog` ADD FULLTEXT KEY `message` (`message`);

--
-- Indexes for table `subnets`
--
ALTER TABLE `subnets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `syslog`
--
ALTER TABLE `syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`level`,`customer`);

--
-- Indexes for table `Traffic_detail`
--
ALTER TABLE `Traffic_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `src` (`auth_id`,`timestamp`,`router_id`,`src_ip`),
  ADD KEY `dst` (`auth_id`,`timestamp`,`router_id`,`dst_ip`);

--
-- Indexes for table `Unknown_mac`
--
ALTER TABLE `Unknown_mac`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`device_id`,`port_id`,`mac`);

--
-- Indexes for table `User_auth`
--
ALTER TABLE `User_auth`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_index` (`id`,`user_id`,`ip_int`,`mac`,`ip`,`deleted`) USING BTREE,
  ADD KEY `deleted` (`deleted`);

--
-- Indexes for table `User_list`
--
ALTER TABLE `User_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `User_stats`
--
ALTER TABLE `User_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

--
-- Indexes for table `User_stats_full`
--
ALTER TABLE `User_stats_full`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

--
-- Indexes for table `variables`
--
ALTER TABLE `variables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--
