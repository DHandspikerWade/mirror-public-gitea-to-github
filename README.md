# Mirror Gitea repos to Github

## What does it do? 

* Creates missing public Gitea repos on Github
* Updates repo description on Github
* Creates a push mirror in Gitea to automatically keep Github up to date

## Required variables 

| Variable | value                        |
|----------|------------------------------|
| GH_TOKEN | API token for github         |
| GT_TOKEN | API token for Gitea instance |
| GH_USER | Github username               |
| GT_USER | Gitea username                |
| GT_HOST | Domain/host of Gitea instance |