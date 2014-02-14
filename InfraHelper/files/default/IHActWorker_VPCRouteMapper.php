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


  $ACTIVITY_NAME = "VPCRouteMapper";
  $ACTIVITY_VERSION = $IHACTIVITY_VERSION;
  $DEBUG = false;

  $task_list="VPCRouteMappertasklist";

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
          cheap_logger($GLOBALS["ACTIVITY_NAME"], $logMsg="Request JSON:");
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

      #get some information about our instance to find out the vpcID later
      $response = $ec2->describe_instances(array(
        'Filter' => array(
          array('Name' => 'instance-id', 'Value' => "$MyInstance"),
        )
      ));

      if($response->isOK())
      {
        $MyVPC = trim((string)$response->body->reservationSet->item->instancesSet->item->vpcId);
        
        ## get our route table based on the tag set for it.
        $response2 = $ec2->describe_route_tables(array(
        'Filter' => array(
            array('Name' => 'tag:Network', 'Value' => "Private Route"),
          )
        ));

        if($response2->isOK())
        {
          $MyRTableID = trim((string)$response2->body->routeTableSet->item->routeTableId);
          $response3 = $ec2->replace_route($MyRTableID, '0.0.0.0/0', array(
              'InstanceId' => $MyInstance
          ));

          if($response3->isOK())
          {
            $successMsg="SUCCESS: VPCRouteMapper: Successfully set the default route on the private route table: ".$MyRTableID." to instance: ".$MyInstance;
            cheap_logger($GLOBALS["ACTIVITY_NAME"], $successMsg);
            return $successMsg;
          }
          else
          {
            $MyErrCode = trim((string)$response3->body->Errors->Error->Message);
            if(preg_match("/CreateRoute/", $MyErrCode))
            {
              $response4 = $ec2->create_route($MyRTableID, '0.0.0.0/0', array(
                 'InstanceId' => $MyInstance
              ));

              if($response4->isOK())
              {
                $successMsg="SUCCESS: VPCRouteMapper: Successfully set the default route on the private route table: ".$MyRTableID." to instance: ".$MyInstance;
                cheap_logger($GLOBALS["ACTIVITY_NAME"], $successMsg);
                return $successMsg;
              }
              else
              {
                $failMsg="FAIL: VPCRouteMapper: There was a problem setting the default routes.";
                cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
                var_dump($response4->body);
                return $failMsg;
              }
            }
            else
            {
              $failMsg="FAIL: VPCRouteMapper: There was a problem setting the default routes.";
              cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
              var_dump($response3->body);
              return $failMsg;
            }
          }
        }
        else
        {
          $failMsg = "FAIL: Unable to get information about the Private route table in this VPC: ".$MyVPC;
          cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
          return $failMsg;
        }
      }
      else
      {
        $failMsg = "FAIL: Unable to talk to the EC2 API. Something is wrong.";
        cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
        return $failMsg;
      }
    }
    else
    {
      $failMsg="FAIL: VPCRouteMapper: We got input that we don't understand: ".$input;
      cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
      return $failMsg;
    }
  }
?>
