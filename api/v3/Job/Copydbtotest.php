<?php
ini_set('display_errors', 1);
set_time_limit(0);   
ini_set('mysql.connect_timeout','0');   
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
  watchdog('debug', 'civicrm_api3_job_copydbtotest');
  
  $return['is_error'] = false;
  
  // fetch settings from the live database  
  //echo('constant("CIVICRM_DSN"): ' . constant("CIVICRM_DSN")) . PHP_EOL;
  
  $db = ['live', 'test'];
  
  // do not use %s it means all chactacters except white spaces use %[^:@?/] instead (all characters except : @ ? and /) 
  list($db['live']['username'], $db['live']['password'], $db['live']['host'], $db['live']['database']) = sscanf(constant("CIVICRM_DSN"), "mysql://%[^:@?/]:%[^:@?/]@%[^:@?/]/%[^:@?/]?new_link=true");
  
  $db['test'] = [
    'username' => $db['live']['username'],
    'password' => $db['live']['password'],
    'host' => $db['live']['host'],
    'database' => 'copytotest-test_civicrm',
  ];
  
  /*echo('$db:<pre>');
  print_r($db);
  echo('</pre>');*/
  
  // connect to drupal database
  /*if(!$link = mysql_connect($db['test']['host'], $db['test']['username'], $db['test']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return $return;
  } 
  elseif(!mysql_select_db('maf-test_drupal', $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', 'maf-test_drupal', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return $return;
  }*/
    
  // enable maintenance mode
  /*$query = sprintf("UPDATE `maf-test_drupal`.drupal_variable SET value = '%s' WHERE name = 'maintenance_mode'", serialize(1));
  echo('$query: ' . $query) . PHP_EOL;
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot enable maintenance mode, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);*/
  
  //mysql_close($link);
  
  // backup database in /var/tmp 
  if(!file_exists('/var/tmp/maf-live_civicrm_copytotest.sql')){
    $cmd = 'cd /var/tmp && mysqldump -u %s -p%s %s > %s_copytotest.sql';
    echo('$cmd: ' . $cmd) . PHP_EOL;
    watchdog('debug', '$cmd: ' . $cmd);
    $cmd = sprintf($cmd, $db['live']['username'], $db['live']['password'], 'maf-live_civicrm', 'maf-live_civicrm');
    
    //echo('$cmd: ' . $cmd) . PHP_EOL;
    exec($cmd, $output, $return_var);

    echo('$output:<pre>');
    print_r($output);
    echo('</pre>');
    watchdog('debug', '$output:<pre>'. print_r($output, TRUE) .'</pre>');
    echo('$return_var: ' . $return_var) . PHP_EOL;
    watchdog('debug', '$return_var: ' . $return_var);
  }
  
  // restore database in /var/tmp
  $cmd = 'cd /var/tmp && mysql -u %s -p%s %s < %s_copytotest.sql';
  echo('$cmd: ' . $cmd) . PHP_EOL;
  watchdog('debug', '$cmd: ' . $cmd);
  $cmd = sprintf($cmd, $db['test']['username'], $db['test']['password'], 'copytotest-test_civicrm', 'maf-live_civicrm');

  //echo('$cmd: ' . $cmd) . PHP_EOL;
  exec($cmd, $output, $return_var);
  
  echo('$output:<pre>');
  print_r($output);
  echo('</pre>');
  watchdog('debug', '$output:<pre>'. print_r($output, TRUE) .'</pre>');
  echo('$return_var: ' . $return_var) . PHP_EOL;
  watchdog('debug', '$return_var: ' . $return_var);
  
  // change civicrm settings
  // connect to database
  /*if(!$link = mysql_connect($db['test']['host'], $db['test']['username'], $db['test']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return $return;
  } 
  elseif(!mysql_select_db('copytotest-test_civicrm', $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', 'copytotest-test_civicrm', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return $return;
  }
    
  // change extensionsDir
  $query = sprintf("UPDATE `copytotest-test_civicrm`.civicrm_setting SET value = '%s' WHERE name = 'extensionsDir'", serialize('/home/maf/www/test/sites/default/civicrm_extensions'));
  echo('$query: ' . $query) . PHP_EOL;
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update Settings, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);
  
  // change Outbound Mail
  // first get the outbaoun mail setting and set outBound_option to 5 (Redirect to Database)
  $query = sprintf("SELECT value FROM `copytotest-test_civicrm`.civicrm_setting WHERE name = 'mailing_backend'");
  echo('$query: ' . $query) . PHP_EOL;
  
  $result = mysql_query($query, $link);
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot get Outbound Mail, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);
  $row = mysql_fetch_assoc($result);
  
  //var_dump($row);
  $value = unserialize($row['value']);
  echo('$value:<pre>');
  print_r($value);
  echo('</pre>');
  
  // change Outbound Mail
  $value['outBound_option'] = 5;
  echo('$value:<pre>');
  print_r($value);
  echo('</pre>');
  
  $query = sprintf("UPDATE `copytotest-test_civicrm`.civicrm_setting SET value = '%s' WHERE name = 'mailing_backend'", serialize($value));
  echo('$query: ' . $query) . PHP_EOL;
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update Outbound Mail, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);
  
  // change SMS Provider
  // change te API URl to a none exsisting
  $query = sprintf("UPDATE `copytotest-test_civicrm`.civicrm_sms_provider SET username = 'a', password = 'a', api_url = 'a'");
  echo('$query: ' . $query) . PHP_EOL;
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update SMS Provider, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);
  
  // disable Scheduled Jobs
  // disable all Scheduled Jobs that send mail
  $except = ['Samedate', 'LinkDonorGroup', 'Gendata', 'Cron', 'cleanup', 'fetch_bounces'];
  // fetch_bounces staat aan op test ???, send_reminder staat aan op test ???, process_sms staat aan op test ???
  //$disable = ['mail_report', 'process_pledge', 'process_respondent', 'process_mailing', 'send_reminder', 'process_sms'];
  $disable = ['mail_report', 'process_pledge', 'process_respondent', 'process_mailing'];
  
  $where = "api_action = '" . implode("' OR api_action = '", $disable) . "'";
  $query = sprintf("UPDATE `copytotest-test_civicrm`.civicrm_job SET is_active = '0' WHERE %s", $where);
  echo('$query: ' . $query) . PHP_EOL;
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot update Scheduled Jobs, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);
  
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
    return $return;
  }*/
    
  // disable maintenance mode
  /*$query = sprintf("UPDATE `maf-test_drupal`.drupal_variable SET value = '%s' WHERE name = 'maintenance_mode'", serialize(0));
  echo('$query: ' . $query) . PHP_EOL;
  if(!$result = mysql_query($query, $link)){
    $return['error_message'][] = sprintf('Cannot disable maintenance mode, error mysql_query %s', mysql_error($link));
    $return['is_error'] = true;
  }
  var_dump($result);*/
  
  // clear cache
  /*$cache_tables = ['drupal_cache', 'drupal_cache_block', 'drupal_cache_bootstrap', 'drupal_cache_field', 'drupal_cache_filter', 'drupal_cache_form', 'drupal_cache_image', 'drupal_cache_menu', 'drupal_cache_page', 'drupal_cache_path', 'drupal_cache_rules', 'drupal_cache_token', 'drupal_cache_update', 'drupal_cache_views', 'drupal_cache_views_data'];
  foreach($cache_tables as $table){
    $query = sprintf("DELETE FROM `maf-test_drupal`.%s WHERE cid <> ''", $table);
    echo('$query: ' . $query) . PHP_EOL;
    if(!$result = mysql_query($query, $link)){
      $return['error_message'][] = sprintf('Cannot set clear cache table %s, error mysql_query %s', $table, mysql_error($link));
      $return['is_error'] = true;
    }
    var_dump($result);
  }*/
  
  //mysql_close($link);
  
  // connect to drupal database
  /*if(!$link = mysql_connect($db['live']['host'], $db['live']['username'], $db['live']['password'])) { 
    $return['error_message'] = sprintf('Cannot connect (mysql), error mysql_connect %s', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return $return;
  } 
  elseif(!mysql_select_db('maf-live_civicrm', $link)) { 
    $return['error_message'] = sprintf('Cannot select database (mysql), database %s, error mysql_select_db %s', 'maf-test_drupal', mysql_error($link));
    $return['is_error'] = true;
    mysql_close($link);
    return $return;
  }
    
  if($return['is_error']){
    $return['error_message'] = implode(', ', $return['error_message']);
  }*/
  
  return $return;
}