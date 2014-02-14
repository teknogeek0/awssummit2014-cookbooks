#
# Cookbook Name:: InfraHelper
# Attributes:: InfraHelper
#
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

# General settings
default['InfraHelper']['base_dir'] = "/opt/InfraHelper"
default['InfraHelper']['IH_queue'] = "PUT SNS QUEUE HERE"
default['InfraHelper']['IHswf_domain'] = "PUT SWF DOMAIN HERE"
default['InfraHelper']['SWF_Region'] = "swf.us-west-2.amazonaws.com"
default['InfraHelper']['EC2_Region'] = "ec2.us-west-2.amazonaws.com"

