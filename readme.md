# README


## Requirements

This script use [PHP](http://php.net) and theses libraries :
* [json](http://php.net/manual/en/json.installation.php) normaly yet enabled
* [intl](http://php.net/manual/eb/book.intl.php) to do some transliteration


## Installation

Install script from git :
```bash
git clone git@github.com:Webelys/glpi_ansible.git /etc/ansible/
```

Create a *variables* file as this in */etc/ansible* :
```
GLPI_SERVER="https://glpi.example.org/plugins/webservices/rest.php"
GLPI_ACCOUNT="user"
GLPI_PASSWORD="password"
```

## GLPI configuration

To use this script, we must :
* install [weservices](http://plugins.glpi-project.org/spip.php?article93) 
* create a rule to allow ansible IP

Note : User account are GLPI user not webeservice account (only IP is used)


## Host Inventory extraction

This script extract :
 * GLPI entities and create this as Ansible Group
 * GLPI entities hierarchy is manage as Ansible Group Children
 * Only GLPI computer items are extrated as host (fqdn name is used)
