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

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'IHResources.php';
  require_once 'IHCommon.php';
  require_once 'AWSSDKforPHP/sdk.class.php';
 
  // Setup
  $swf = new AmazonSWF(array('default_cache_config' => '/tmp/secure-dir'));
  $swf->set_region($SWF_Region);
  $workflow_domain = $IHSWFDomain;
  $workflow_type_name = "IHWorkFlowMain";


  $ACTIVITY_NAME = "EIPMapper";
  $ACTIVITY_VERSION = $IHACTIVITY_VERSION;
  $DEBUG = false;

  $task_list="EIPMappertasklist";

  #look for something to do.
  $response = $swf->poll_for_activity_task(array(
      'domain' => $workflow_domain,
      'taskList' => array(
          'name' => $task_list
      )
  ));
  
  if ($DEBUG) {
      print_r($response->body);
  }
             
  if ($response->isOK()) 
  {    
    $task_token = (string) $response->body->taskToken;
      
    if (!empty($task_token)) 
    {                    
        $activity_input = $response->body->input;
        #now that we have input, go and pass this on to the actual brains of our worker
        $activity_output = execute_task($activity_input);
        
        $complete_opt = array(
            'taskToken' => $task_token,
            'result' => $activity_output
        );
        
        #respond with the results of the actions in the execute_task
        $complete_response = $swf->respond_activity_task_completed($complete_opt);
        
        if ($complete_response->isOK())
        {
          cheap_logger($GLOBALS["ACTIVITY_NAME"], "RespondActivityTaskCompleted SUCCESS");
        } 
        else 
        {
          // a real application may want to report this failure and retry
          cheap_logger($GLOBALS["ACTIVITY_NAME"], "RespondActivityTaskCompleted FAIL");
          cheap_logger($GLOBALS["ACTIVITY_NAME"], "Response body:");
          print_r($complete_response->body);
          cheap_logger($GLOBALS["ACTIVITY_NAME"], "Request JSON:");
          echo json_encode($complete_opt) . "\n";
        }
    } 
    else 
    {
        cheap_logger($GLOBALS["ACTIVITY_NAME"], "PollForActivityTask received empty response.");
    }
  } 
  else 
  {
      cheap_logger($GLOBALS["ACTIVITY_NAME"], "Looks like we had trouble talking to SWF and getting a valid response.");
      print_r($response->body);
  }

    
  function execute_task($input) 
  {
    if($input != "")
    {
      $MyInstance=$input;

      $ec2 = new AmazonEC2(array('default_cache_config' => '/tmp/secure-dir'));
      $ec2->set_region($GLOBALS["EC2_Region"]);
      $eip_opt = array(
      'Domain'=> "vpc"
      );
      
      ### do look up for existing EIP

      ##test response
      if()
      {

      }
      else
      {
        #looks like we don't yet have an EIP available, so get one now.
        #get an EIP, this can fail if you are near your quota for EIPs
        $response = $ec2->allocate_address($eip_opt);

        if($response->isOK())
        {
          $bodyarray=$response->body->to_array();
          $MyIpAddr=$bodyarray["publicIp"];
          $MyAllocId=$bodyarray["allocationId"];

          $assocAddr_opt = array(
          'AllocationId'=> "$MyAllocId"
          );

          #we have an EIP, and an Allocation ID, so map this to our instance
          $response2 = $ec2->associate_address($MyInstance,"",$assocAddr_opt);
          if($response2->isOK())
          {
            $successMsg="SUCCESS: EIPMapper: Successfully created EIP with IP: ".$MyIpAddr.", and attached it to instance: ".$MyInstance.PHP_EOL;
            cheap_logger($GLOBALS["ACTIVITY_NAME"], $successMsg);
            return $successMsg;
          }
          else
          {
            $failMsg="FAIL: EIPMapper: There was a problem attaching the EIP to the instance." .PHP_EOL;
            cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
            var_dump($response2->body);
            $response3 = $ec2->release_address(array('AllocationId'=>"$MyAllocId"));
            if($response3->isOK())
            {
              $failMsg.="I was able to release the IPaddress.".PHP_EOL;
            }
            else
            {
              $failMsg.="I was unable to release the IPaddress.".PHP_EOL;
              var_dump($response3->body);
            }
            return $failMsg;
          }
        }
        else
        {
          $failMsg="FAIL: EIPMapper: There was a problem getting an IP address." . PHP_EOL;
          cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
          var_dump($response->body);
          return $failMsg;
        }
      }
    }
    else
    {
      $failMsg="FAIL: EIPMapper: We got input that we don't understand: ".$input. PHP_EOL;
      cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
      return $failMsg;
    }
  }
?>
