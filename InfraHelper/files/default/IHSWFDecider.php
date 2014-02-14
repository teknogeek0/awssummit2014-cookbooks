<?php
/*
 * Copyright 2012 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
  require_once 'IHResources.php';
  require_once 'IHCommon.php';
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'HistoryEventIterator.php';
  
  $ACTIVITY_NAME = "IHDecider";

/*
 * A decider can be written by modeling the workflow as a state machine. 
 * For complex workflows, this is the easiest model to use.
 *
 * The decider reads the history to figure out which state the workflow is currently in,
 * and makes a decision based on the current state.
 *
 * This implementation of the decider ignores activity failures.
 * You can handle them by adding more states.
 * This decider also only supports having a single activity open at a time.
 */
abstract class BasicWorkflowWorkerStates {
  // A new workflow is in this state
  const START = 0;
  // If a timer is open, and not an activity.
  const TIMER_OPEN = 1;
  // If an activity is open, and not a timer.
  const ACTIVITY_OPEN = 2;
  // If both a timer and an activity are open.
  const TIMER_AND_ACTIVITY_OPEN = 3;
  // Nothing is open.
  const NOTHING_OPEN = 4;
}

/*
 * At some point it makes sense to separate polling logic and worker logic, but we've left
 * them together here for simplicity.
 */
class BasicWorkflowWorker
{
  const DEBUG = false;

  const WORKFLOW_NAME = "IHWorkFlowMain";
  const WORKFLOW_VERSION = "1.0";

  // If you increase this value, you should also
  // increase your workflow execution timeout accordingly so that a 
  // new generation is started before the workflow times out.
  const EVENT_THRESHOLD_BEFORE_NEW_GENERATION = 150;

  protected $swf;
  protected $domain;
  protected $task_list;
     
  public function __construct(AmazonSWF $swf_service, $domain, $task_list) {
    $this->domain = $domain;
    $this->task_list = $task_list;
    $this->swf = $swf_service;
  }

  public function start() {
    $this->_poll();
  }

  protected function _poll()
  {
    while (true)
    {
      $opts = array(
        'domain' => $this->domain,
        'taskList' => array(
            'name' => $this->task_list
        )
      );
      
      $response = $this->swf->poll_for_decision_task($opts);
      
                
      if ($response->isOK()) {
          $task_token = (string) $response->body->taskToken;
                         
        if (!empty($task_token)) 
        {
          if (self::DEBUG) {
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "Got history; handing to decider");
          }
                             
          $history = $response->body->events();
          
          try {
            $decision_list = self::_decide(new HistoryEventIterator($this->swf, $opts, $response));
          } catch (Exception $e) {
            // If failed decisions are recoverable, one could drop the task and allow it to be redriven by the task timeout.
            echo 'Failing workflow; exception in decider: ', $e->getMessage(), "\n", $e->getTraceAsString(), "\n";
            $decision_list = array(
              wrap_decision_opts_as_decision('FailWorkflowExecution', array(
                'reason' => substr('Exception in decider: ' . $e->getMessage(), 0, 256),
                'details' => substr($e->getTraceAsString(), 0, 32768)
              ))
            );
          }
          
          if (self::DEBUG) {
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "Responding with decisions: ");
            print_r($decision_list);
          }
          
          $complete_opt = array(
            'taskToken' => $task_token,
            'decisions'=> $decision_list
          );

          $complete_response = $this->swf->respond_decision_task_completed($complete_opt);
          if ($complete_response->isOK())
          {
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "RespondDecisionTaskCompleted SUCCESS");
            exit;
          } 
          else 
          {
            // a real application may want to report this failure and retry
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "RespondDecisionTaskCompleted FAIL");
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "Response body: ");
            print_r($complete_response->body);
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "Request JSON: ");
            echo json_encode($complete_opt) . "\n";
          }
        } 
        else 
        {
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "PollForDecisionTask received empty response");
            exit;
        }
      } 
      else
      {
        cheap_logger($GLOBALS["ACTIVITY_NAME"], "ERROR: ");
        print_r($response->body);
        
        sleep(2);
      }
    }        
  }
    
  /**
   * A decider inspects the history of a workflow and then schedules more tasks based on the current state of 
   * the workflow.
   */
  protected static function _decide($history) {       
    $workflow_state = BasicWorkflowWorkerStates::START;
    
    $timer_opts = null;
    $activity_opts = null;
    $continue_as_new_opts = null;
    $max_event_id = 0;

    foreach ($history as $event) {
        $event_type = (string) $event->eventType;
        self::_process_event($event, $workflow_state, $activity_opts, $max_event_id);
        if($activity_opts!=null)
        {
          break;
        }
    }
    
    if (is_array($activity_opts))
    {
      $activity_decision = wrap_decision_opts_as_decision('ScheduleActivityTask', $activity_opts);
    }
    else
    {
      $activity_decision = array ('decisionType' => $activity_opts);
    }

    return array(
      $activity_decision
    );
  }

    /*
     * By reading events in the history, we can determine which state the workflow is in.
     * And then, based on the current state of the workflow, the decider knows what should happen next.
     */
  protected static function _process_event($event, &$workflow_state, &$activity_opts, &$max_event_id) {
    $event_type = (string) $event->eventType;
    $max_event_id = max($max_event_id, intval($event->eventId));
    
    if (BasicWorkflowWorker::DEBUG) {
        cheap_logger($GLOBALS["ACTIVITY_NAME"], "event type: $event_type");
        print_r($event);
    }
    
    switch ($event_type)
    {
      case 'ActivityTaskCompleted':
        if ($workflow_state === BasicWorkflowWorkerStates::ACTIVITY_OPEN) { 
            $workflow_state = BasicWorkflowWorkerStates::NOTHING_OPEN;
        } else if ($workflow_state === BasicWorkflowWorkerStates::TIMER_AND_ACTIVITY_OPEN) {
            $workflow_state = BasicWorkflowWorkerStates::TIMER_OPEN;
        }
        $ActivityResult= $event->activityTaskCompletedEventAttributes->result;
        $activity_opts = NATThingy($event_type, $ActivityResult);
        break;
      case 'WorkflowExecutionStarted':
        $workflow_state = BasicWorkflowWorkerStates::START;
        if($max_event_id>3)
        {
          break;
        }
        $event_attributes = $event->workflowExecutionStartedEventAttributes;
        $workflow_input = $event_attributes->input;
        if (BasicWorkflowWorker::DEBUG) {
            cheap_logger($GLOBALS["ACTIVITY_NAME"], "Workflow input: ");
            print_r($workflow_input);
        }
        $activity_opts = NATThingy($event_type, $event_attributes);
        break;
    }
  }
}

function wrap_decision_opts_as_decision($decision_type, $decision_opts) 
{
  return array(
    'decisionType' => $decision_type,
    strtolower(substr($decision_type, 0, 1)) . substr($decision_type, 1) . 'DecisionAttributes' => $decision_opts
  );
}

function NATThingy ($event_type, $event_attributes)
{
    if($event_type == "WorkflowExecutionStarted")
    {
      $workflow_input = $event_attributes->input;
    }
    else
    {
      $workflow_input = $event_attributes;
    }

    if (preg_match("/EventType=autoscaling:(.*):Instance=(.*)/", $workflow_input, $matches))
    {
      $ASaction=$matches[1];
      $MyInstance=$matches[2];

      if ( $ASaction == "EC2_INSTANCE_LAUNCH")
      {
        $logMsg = "Doing: EIPMapper".PHP_EOL;
        $activity_opts = create_activity_opts_from_workflow_input("EIPMapper", "1.0", $MyInstance, "EIPMappertasklist");
      }
      elseif($ASaction == "EC2_INSTANCE_TERMINATE")
      {
        $logMsg = "Doing: ChefRemoveClientNode".PHP_EOL;
        $activity_opts = create_activity_opts_from_workflow_input("ChefRemoveClientNode", "1.0", $MyInstance, "ChefRemoveClientNodetasklist");
      }
      else
      {
        $failMsg="FAIL: This isn't a task we know how to understand: ".$ASaction. PHP_EOL;
        cheap_logger($GLOBALS["ACTIVITY_NAME"], $failMsg);
        exit;
      }
      cheap_logger($GLOBALS["ACTIVITY_NAME"], $logMsg);
      return $activity_opts;
    }
    elseif (preg_match("/SUCCESS: (\w*): .*:.*:? (i-.*)/", $event_attributes, $matches))
    {
      $justcompleted = $matches[1];
      $MyInstance = $matches[2];
      if ($justcompleted == "EIPMapper")
      {
        $logMsg = "Doing: SrcDestCheckSet".PHP_EOL;
        $activity_opts = create_activity_opts_from_workflow_input("SrcDestCheckSet", "1.0", $MyInstance, "SrcDestCheckSettasklist");
      }
      elseif ($justcompleted == "SrcDestCheckSet")
      {
        $logMsg = "Doing: VPCRouteMapper".PHP_EOL;
        $activity_opts = create_activity_opts_from_workflow_input("VPCRouteMapper", "1.0", $MyInstance, "VPCRouteMappertasklist");
      }
      elseif ($justcompleted == "VPCRouteMapper")
      {
        $logMsg = "NAT Workflow complete!".PHP_EOL;
        $activity_opts = "CompleteWorkflowExecution";
      }
      elseif ($justcompleted == "ChefRemoveClientNode")
      {
        $logMsg = "Chef Remove Client Workflow complete!".PHP_EOL;
        $activity_opts = "CompleteWorkflowExecution";
      }
      else
      {
        cheap_logger($GLOBALS["ACTIVITY_NAME"], "Something go boom. You broke it.");
        exit;
      }

      cheap_logger($GLOBALS["ACTIVITY_NAME"], $logMsg);
      return $activity_opts;
    }
    elseif (preg_match("/FAIL: (\w*):? (.*:?.*)/", $event_attributes, $matches))
    {
      $justcompleted = $matches[1];

      cheap_logger($GLOBALS["ACTIVITY_NAME"], "We failed doing what we need to do!! We were trying to $justcompleted");
      ##need to terminate workflow?
      exit;
    }
    else
    {
      cheap_logger($GLOBALS["ACTIVITY_NAME"], "FAIL: We got input that we don't understand: $workflow_input");
      exit;
    }
}

function create_activity_opts_from_workflow_input($activityName, $activityVersion, $input, $taskList)
{
  $activity_opts = array(
    'activityType' => array(
        'name' => "$activityName",
        'version' => "$activityVersion"
    ),
    'activityId' => 'myActivityId-' . time(),
    'input' => "$input",
    'taskList' => array('name' => "$taskList"),
    'scheduleToCloseTimeout' => '300',
    'scheduleToStartTimeout' => '300',
    'startToCloseTimeout' => '300',
    'heartbeatTimeout' => 'NONE'
  );

  return $activity_opts;
}

?>
