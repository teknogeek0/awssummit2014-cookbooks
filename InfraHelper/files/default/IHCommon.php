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
  require_once 'AWSSDKforPHP/sdk.class.php';

  function cheap_logger($proc, $logMsg) 
  {
    $date = new DateTime();
    if($proc != "" && $logMsg != "")
    {
      echo $date->format('d/M/o:H:i:s O')." ".gethostname()." ".$proc."[".getmypid()."]".": ".$logMsg.PHP_EOL;
    }
    else
    {
      echo "Got a logging message that was missing information! Ya broke it!".PHP_EOL;
    }
  }

?>