<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'Cron:Job.Copydbtotest',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'Copy live civicrm database to test',
      'description' => 'Copy the maf-live_civicrm database to the maf-test_civicrm and change a couple settings.',
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'Copydbtotest',
      'parameters' => '',
    ),
  ),
);