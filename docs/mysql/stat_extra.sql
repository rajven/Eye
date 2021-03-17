
--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `building`
--
ALTER TABLE `building`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `option` (`option_id`);

--
-- Индексы таблицы `config_options`
--
ALTER TABLE `config_options`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `connections`
--
ALTER TABLE `connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`),
  ADD KEY `device_id` (`device_id`,`port_id`);

--
-- Индексы таблицы `Customers`
--
ALTER TABLE `Customers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_model_id` (`device_model`),
  ADD KEY `ip` (`ip`),
  ADD KEY `device_type` (`device_type`);

--
-- Индексы таблицы `device_l3_interfaces`
--
ALTER TABLE `device_l3_interfaces`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `device_ports`
--
ALTER TABLE `device_ports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `port` (`port`),
  ADD KEY `target_port_id` (`target_port_id`);

--
-- Индексы таблицы `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `dhcp_log`
--
ALTER TABLE `dhcp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`action`);

--
-- Индексы таблицы `dns_cache`
--
ALTER TABLE `dns_cache`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dns` (`dns`,`ip`),
  ADD KEY `timestamp` (`timestamp`);

--
-- Индексы таблицы `Filter_list`
--
ALTER TABLE `Filter_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Name` (`name`);

--
-- Индексы таблицы `Group_filters`
--
ALTER TABLE `Group_filters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `GroupId` (`group_id`,`filter_id`);

--
-- Индексы таблицы `Group_list`
--
ALTER TABLE `Group_list`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `mac_history`
--
ALTER TABLE `mac_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mac` (`mac`,`timestamp`),
  ADD KEY `ip` (`ip`,`timestamp`),
  ADD KEY `timestamp` (`timestamp`,`mac`),
  ADD KEY `timestamp_2` (`timestamp`,`ip`);

--
-- Индексы таблицы `mac_vendors`
--
ALTER TABLE `mac_vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oui` (`oui`);

--
-- Индексы таблицы `OU`
--
ALTER TABLE `OU`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `Queue_list`
--
ALTER TABLE `Queue_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Индексы таблицы `remote_syslog`
--
ALTER TABLE `remote_syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`date`,`device_id`,`ip`);
ALTER TABLE `remote_syslog` ADD FULLTEXT KEY `message` (`message`);

--
-- Индексы таблицы `subnets`
--
ALTER TABLE `subnets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_int_start` (`ip_int_start`,`ip_int_stop`),
  ADD KEY `dhcp` (`dhcp`,`office`,`hotspot`,`static`);

--
-- Индексы таблицы `syslog`
--
ALTER TABLE `syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp_2` (`timestamp`,`level`,`customer`);

--
-- Индексы таблицы `Traffic_detail`
--
ALTER TABLE `Traffic_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `src` (`auth_id`,`timestamp`,`router_id`,`src_ip`),
  ADD KEY `dst` (`auth_id`,`timestamp`,`router_id`,`dst_ip`);

--
-- Индексы таблицы `Unknown_mac`
--
ALTER TABLE `Unknown_mac`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`device_id`,`port_id`,`mac`);

--
-- Индексы таблицы `User_auth`
--
ALTER TABLE `User_auth`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_index` (`id`,`user_id`,`ip_int`,`mac`,`ip`,`deleted`) USING BTREE,
  ADD KEY `deleted` (`deleted`);

--
-- Индексы таблицы `User_auth_alias`
--
ALTER TABLE `User_auth_alias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`);

--
-- Индексы таблицы `User_list`
--
ALTER TABLE `User_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `users` (`id`,`ou_id`,`enabled`,`blocked`,`deleted`);

--
-- Индексы таблицы `User_stats`
--
ALTER TABLE `User_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

--
-- Индексы таблицы `User_stats_full`
--
ALTER TABLE `User_stats_full`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

--
-- Индексы таблицы `variables`
--
ALTER TABLE `variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `clear_time` (`clear_time`,`created`);

--
-- Индексы таблицы `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `building`
--
ALTER TABLE `building`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `config`
--
ALTER TABLE `config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT для таблицы `config_options`
--
ALTER TABLE `config_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT для таблицы `connections`
--
ALTER TABLE `connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Customers`
--
ALTER TABLE `Customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `device_l3_interfaces`
--
ALTER TABLE `device_l3_interfaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `device_ports`
--
ALTER TABLE `device_ports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `device_types`
--
ALTER TABLE `device_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dhcp_log`
--
ALTER TABLE `dhcp_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dns_cache`
--
ALTER TABLE `dns_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Filter_list`
--
ALTER TABLE `Filter_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Group_filters`
--
ALTER TABLE `Group_filters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Group_list`
--
ALTER TABLE `Group_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mac_history`
--
ALTER TABLE `mac_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mac_vendors`
--
ALTER TABLE `mac_vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `OU`
--
ALTER TABLE `OU`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Queue_list`
--
ALTER TABLE `Queue_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `remote_syslog`
--
ALTER TABLE `remote_syslog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `subnets`
--
ALTER TABLE `subnets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `syslog`
--
ALTER TABLE `syslog`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Traffic_detail`
--
ALTER TABLE `Traffic_detail`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Unknown_mac`
--
ALTER TABLE `Unknown_mac`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_auth`
--
ALTER TABLE `User_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_auth_alias`
--
ALTER TABLE `User_auth_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_list`
--
ALTER TABLE `User_list`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_stats`
--
ALTER TABLE `User_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_stats_full`
--
ALTER TABLE `User_stats_full`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `variables`
--
ALTER TABLE `variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
