<?php
ini_set('display_errors', 1);
set_time_limit(0);   
ini_set('mysql.connect_timeout','0');   
ini_set('default_socket_timeout','0');   
ini_set('max_execution_time', '0');

/**
 * Job.Copydbtotest API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_copydbtotest_spec(&$spec) {
}

/**
 * Job.Copydbtotest API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_copydbtotest($params) {  
  $return['is_error'] = false;
  
  // fetch settings from the live database    
  $db = ['live', 'test'];
  
  // do not use %s it means all chactacters except white spaces use %[^:@?/] instead (all characters except : @ ? and /) 
  list($db['live']['username'], $db['live']['password'], $db['live']['host'], $db['live']['database']) = sscanf(constant("CIVICRM_DSN"), "mysql://%[^:@?/]:%[^:@?/]@%[^:@?/]/%[^:@?/]?new_link=true");
  
  $db['test'] = [
    'username' => $db['live']['username'],
    'password' => $db['live']['password'],
    'host' => $db['live']['host'],
    'database' => 'maf-test_civicrm',
  ];
    
  // connect to drupal database
  if(!$link = mysql_connect($db['test']['host'], $db['test']['username'], $db['test']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  } 
  elseif(!mysql_select_db('maf-test_drupal', $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', 'maf-test_drupal', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  }
    
  // enable maintenance mode
  $query = sprintf("UPDATE `%s`.drupal_variable SET value = '%s' WHERE name = 'maintenance_mode'", 'maf-test_drupal', serialize(1));
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot enable maintenance mode, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  mysql_close($link);
  
  // copy live database to test
  // connect to live database
  if(!$link = mysql_connect($db['live']['host'], $db['live']['username'], $db['live']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  } 
  elseif(!mysql_select_db($db['live']['database'], $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', $db['live']['database'], mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  }
  
  // get all tables
  $query = sprintf("SHOW TABLES");
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot get tables from database %s, error mysql_query %s', $db['live']['database'], mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  }
  
  $tables = [];
  while($row = mysql_fetch_row($result)) {  
    
    echo('Copy table: ' . $row[0]) . PHP_EOL;
    
    // backup database in /var/tmp 
    // consulted with Matthijs, he must back up each time all over again
    //if(!file_exists(sprintf('/var/tmp/%s_copytotest_%s.sql', 'maf-live_civicrm_bak', $row[0]))){ // 
      $cmd = 'cd /var/tmp/maf-live_civicrm_bak_copytotest && mysqldump -u %s -p%s %s %s > %s_copytotest_%s.sql';
      $cmd = sprintf($cmd, $db['live']['username'], $db['live']['password'], 'maf-live_civicrm', $row[0], 'maf-live_civicrm_bak', $row[0]);
      exec($cmd, $output, $return_var);
      
      //var_dump($output);
      //var_dump($return_var);
    //}
    
    // restore database in /var/tmp
    $cmd = 'cd /var/tmp/maf-live_civicrm_bak_copytotest && mysql -u %s -p%s %s < %s_copytotest_%s.sql';
    $cmd = sprintf($cmd, $db['test']['username'], $db['test']['password'], $db['test']['database'], 'maf-live_civicrm_bak', $row[0]);
    exec($cmd, $output, $return_var);
    
    //var_dump($output);
    //var_dump($return_var);
  } 
  
  // change civicrm settings
  // connect to database
  if(!$link = mysql_connect($db['test']['host'], $db['test']['username'], $db['test']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  } 
  elseif(!mysql_select_db($db['test']['database'], $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', $db['test']['database'], mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  }
    
  // change extensionsDir
  $query = sprintf("UPDATE `%s`.civicrm_setting SET value = '%s' WHERE name = 'extensionsDir'", $db['test']['database'], serialize('/home/maf/www/test/sites/default/civicrm_extensions'));
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update Settings, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  // change Outbound Mail
  // first get the outbaoun mail setting and set outBound_option to 5 (Redirect to Database)
  $query = sprintf("SELECT value FROM `%s`.civicrm_setting WHERE name = 'mailing_backend'", $db['test']['database']);
  
  $result = mysql_query($query, $link);
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot get Outbound Mail, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  $row = mysql_fetch_assoc($result);
  $value = unserialize($row['value']);
  
  // change Outbound Mail
  $value['outBound_option'] = 5;
  
  $query = sprintf("UPDATE `%s`.civicrm_setting SET value = '%s' WHERE name = 'mailing_backend'", $db['test']['database'], serialize($value));
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update Outbound Mail, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  // change SMS Provider
  // change te API URl to a none exsisting
  $query = sprintf("UPDATE `%s`.civicrm_sms_provider SET username = 'a', password = 'a', api_url = 'a'", $db['test']['database']);
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update SMS Provider, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  // disable Scheduled Jobs
  // disable all Scheduled Jobs that send mail
  $except = ['Samedate', 'LinkDonorGroup', 'Gendata', 'Cron', 'cleanup', 'fetch_bounces'];
  // fetch_bounces staat aan op test ???, send_reminder staat aan op test ???, process_sms staat aan op test ???
  //$disable = ['mail_report', 'process_pledge', 'process_respondent', 'process_mailing', 'send_reminder', 'process_sms'];
  $disable = ['mail_report', 'process_pledge', 'process_respondent', 'process_mailing'];
  
  $where = "api_action = '" . implode("' OR api_action = '", $disable) . "'";
  $query = sprintf("UPDATE `%s`.civicrm_job SET is_active = '0' WHERE %s", $db['test']['database'], $where);
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update Scheduled Jobs, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  mysql_close($link); 
  
  // connect to drupal database
  if(!$link = mysql_connect($db['test']['host'], $db['test']['username'], $db['test']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link); 
    return $return;
  } 
  elseif(!mysql_select_db('maf-test_drupal', $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', 'maf-test_drupal', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link); 
    return civicrm_api3_create_error($return);
  }
    
  // disable maintenance mode
  $query = sprintf("UPDATE `%s`.drupal_variable SET value = '%s' WHERE name = 'maintenance_mode'", 'maf-test_drupal', serialize(0));
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot disable maintenance mode, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  
  // clear cache
  $cache_tables = ['drupal_cache', 'drupal_cache_block', 'drupal_cache_bootstrap', 'drupal_cache_field', 'drupal_cache_filter', 'drupal_cache_form', 'drupal_cache_image', 'drupal_cache_menu', 'drupal_cache_page', 'drupal_cache_path', 'drupal_cache_rules', 'drupal_cache_token', 'drupal_cache_update', 'drupal_cache_views', 'drupal_cache_views_data'];
  foreach($cache_tables as $table){
    $query = sprintf("DELETE FROM `maf-test_drupal`.%s WHERE cid <> ''", $table);
    if(!$result = mysql_query($query, $link)){
      $return['error_message'][] = sprintf('Cannot set clear cache table %s, error mysql_query %s', $table, mysql_error($link));
      $return['is_error'] = true;
    }
  }
  
  mysql_close($link);
  
  // reconnect to the live database
  if(!$link = mysql_connect($db['live']['host'], $db['live']['username'], $db['live']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  } 
  elseif(!mysql_select_db($db['live']['database'], $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', $db['live']['database'], mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return civicrm_api3_create_error($return);
  }
    
  if($return['is_error']){
    $return['error_message'] = implode(', ', $return['error_message']);
    return civicrm_api3_create_error($return);
  }
  
  return civicrm_api3_create_success($return);
  exit();
}