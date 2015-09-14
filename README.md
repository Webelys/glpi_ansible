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
ln -s /etc/ansible/glpi-ansible/hosts /etc/ansible/
```

To set GLPI inventory as optional hosts Ansible file
```bash
ln -s /etc/ansible/glpi_ansible/hosts /etc/ansible/glpi.sh
```

Create the configuration file `/etc/ansible/variables`:

```bash
GLPI_SERVER="https://glpi.example.org/plugins/webservices/rest.php"
GLPI_ACCOUNT="user"
GLPI_PASSWORD="password"
```

Note: The user is a real GLPI user must be used, _not_ a Webservice user.


## Usage

```bash
ansible -i /etc/ansible/glpi.sh Rootentity -m ping
```
or 
```bash
ansible Rootentity -m ping
```


To debug the script:

```bash
# Output the cached JSON data, as used by Ansible:
./glpi.sh --list

# Reset and output the freshJSON data, as used by Ansible:
./glpi.sh --list --cache PT0S

# Show GLPI Webservice requests (debug mode) :
./glpi.sh -d

# Show help
./glpi.sh -h
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
