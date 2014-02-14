#
# Cookbook Name:: wordpress
# Recipe:: default
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

include_recipe 'deploy'

node[:deploy].each do |application, deploy|

  template "#{deploy[:current_path]}/wp-config.php" do
    source "wp-config.php.erb"
    owner "root"
    group "root"
    mode "0644"
    variables(
      :database        => node['wordpress']['db']['database'],
      :user            => node['wordpress']['db']['user'],
      :password        => node['wordpress']['db']['password'],
      :dbhost          => node['wordpress']['dbhost'],
      :lang            => node['wordpress']['languages']['lang'],
      :cachenode       => node['wordpress']['cachenode']
    )
  end
end
