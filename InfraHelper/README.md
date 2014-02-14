InfraHelper Cookbook
=============

Installs InfraHelper scripts, config and cronjobs. This is for use in the Taking AWS Operations to the Next Level bootcamp, Lab 3.

Requirements
------------
Only tested on Amazon Linux, but should work anywhere that PHP and the AWS PHP-SDK can be installed.

e.g.
#### packages
- `php` - InfraHelper is currently written in PHP
- `php-amazon-sdk` - InfraHelper makes use of the AWS SDKs to talk to various services

Attributes
----------

e.g.
#### InfraHelper::default
<table>
  <tr>
    <th>Key</th>
    <th>Type</th>
    <th>Description</th>
    <th>Default</th>
  </tr>
  <tr>
    <td><tt>['InfraHelper']['base_dir']</tt></td>
    <td>String</td>
    <td>Dir to install php scripts</td>
    <td><tt>"/opt/InfraHelper"</tt></td>
  </tr>
  <tr>
    <td><tt>['InfraHelper']['IH_queue']</tt></td>
    <td>String</td>
    <td>SQS queue that will take in ASG notifications</td>
    <td><tt>none</tt></td>
  </tr>
  <tr>
    <td><tt>['InfraHelper']['IHswf_domain']</tt></td>
    <td>String</td>
    <td>Existing SWF Domain</td>
    <td><tt>none</tt></td>
  </tr>
  <tr>
    <td><tt>['InfraHelper']['SWF_Region']</tt></td>
    <td>String</td>
    <td>The region where the SWF domain exists</td>
    <td><tt>"swf.us-west-2.amazonaws.com"</tt></td>
  </tr>
  <tr>
    <td><tt>['InfraHelper']['EC2_Region']</tt></td>
    <td>String</td>
    <td>The EC2 region that we'll be working with</td>
    <td><tt>"ec2.us-west-2.amazonaws.com"</tt></td>
  </tr>
</table>

Usage
-----
#### InfraHelper::default

e.g.
Just include `InfraHelper` in your node's `run_list`:

```json
{
  "name":"my_node",
  "run_list": [
    "recipe[InfraHelper]"
  ]
}
```
