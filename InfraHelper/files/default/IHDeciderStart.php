<?php
  /*
* Copyright 2012 Amazon.com, Inc. or its affiliates. All Rights Reserved.
*
* Licensed under the Apache License, Version 2.0 (the "License").
* You may not use this file except in compliance with the License.
* A copy of the License is located at
*
* http://aws.amazon.com/apache2.0
*
* or in the "license" file accompanying this file. This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/
  ## start decider workers

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'IHResources.php';
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHSWFDecider.php';

  // Setup
	$swf = new AmazonSWF(array('default_cache_config' => '/tmp/secure-dir'));
  $swf->set_region($SWF_Region);
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";
	$activity_task_list = "mainWorkFlowTaskList";
	$decider_task_list = "mainWorkFlowTaskList";

  $workflow_worker = new BasicWorkflowWorker($swf, $workflow_domain, $decider_task_list);
  $workflow_worker->start();

?>
