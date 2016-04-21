# GLPI Ansible

A PHP script to retrieve [dynamic
inventory](http://docs.ansible.com/ansible/intro_dynamic_inventory.html) for
[Ansible](http://www.ansible.com/) from [GLPI](http://www.glpi-project.org/).


## Requirements

This script use [PHP](http://php.net) and theses libraries:
* [json](http://php.net/manual/en/json.installation.php);
* [intl](http://php.net/manual/eb/book.intl.php) to do some transliteration.

```bash
# To install requirements on Debian/Ubuntu:
sudo apt-get install php5-cli php5-json php5-intl
```


## GLPI configuration

To use this script:

* Install [Webservices](http://plugins.glpi-project.org/spip.php?article93) plugin;
* Then create a Webservice client:
  * Set Ansible server IP address into `IPv4 address range`;
  * Webservice `user name` and `password` fields must be left _empty_.


## Installation

Install this script from sources via Git into Ansible configuration dir:

```bash
git clone git@github.com:Webelys/glpi_ansible.git /etc/ansible/glpi_ansible/
```

To set GLPI inventory as default hosts Ansible file
```bash
ln -s /etc/ansible/glpi-ansible/glpi.php /etc/ansible/hosts
```

To set GLPI inventory as optional hosts Ansible file
```bash
ln -s /etc/ansible/glpi_ansible/glpi.php /etc/ansible/glpi
```

Create the configuration file `/etc/ansible/glpi.ini`:

```ini
# Ansible external inventory script settings for GLPI
#

# Define an GLPI user with access to GLPI API which will be used to
# perform required queries to obtain infromation to generate the Ansible
# inventory output.
#
[glpi]
username = "glpi_user"
password = "glpi_password"
url = "http://localhost/glpi/plugins/webservices/rest.php"
```

Note: The user is a real GLPI user must be used, _not_ a Webservice user.

## Help - cli mode

```bash
Usage: ./glpi.php [options]

  --glpi      -g      : GLPI "rest.php" webservice URL (default: "https://glpi.webelys.com/plugins/webservices/rest.php")
  --username  -u      : GLPI user name
  --password  -p      : GLPI user password
  --list              : Return a complete JSON document (default when called by Ansible)
  --host [hostname]   : [Not implemented yet] Return vars associated to this hostname
  --cache [time]      : Set duration of local cache (default: "P01D" (P01D = 1 day, PT0S=no cache)
  --debug     -d      : Display debug information (default disabled)
  --help      -h      : display this screen

Any other options are used for REST call.
All options can be set  in glpi.ini file
```


## Usage

```bash
ansible -i /etc/ansible/glpi Rootentity -m ping
```
or 
```bash
ansible Rootentity -m ping
```


To debug the script:

```bash
cd /etc/ansible/
# Output the cached JSON data, as used by Ansible:
./glpi --list

# Reset and output the freshJSON data, as used by Ansible:
./glpi --list --cache PT0S

# Show GLPI Webservice requests (debug mode) :
./glpi -d

# Show help
./glpi -h
```

## Extracted Data

This script extract:

* GLPI _Entities_ are translated to Ansible _Groups_
  * GLPI Entities Hierarchy is converted to Ansible Groups _Children_
* Only GLPI Computer items are extrated as host (FQDN name is used when
  _domain_ is set)

Sample of the JSON output:

```json
{
  "Rootentity": {
    "hosts": [
      "my_computer_1",
      "my_computer_3"
    ],
    "children": [
      "my_child_entity_1"
    ]
  },
  "my_child_entity_1": {
    "hosts": [
      "my_computer_2"
    ],
    "children": []
  }
}
```
