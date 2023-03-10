# Cerrajero APP
This microservice wants to centralize all Authentication and Authorization for all applications

## Install
You need installed docker, create a `.env` file copying `.env.dev`  and run the next commands using the Makefile: 

- `make build` to build the containers
- `make start` to start the containers
- `make stop` to stop the containers
- `make help` to review all commands

## Run
You can create a virtualhost editing your hosts file, then you can execute the makefile start command and access directly to:
- [localhost](http://localhost)
- [bodeguero.local](http://bodeguero.local)

### Linux & Unix
`sudo -- sh -c -e "echo '127.0.0.1  bodeguero.local' >> /etc/hosts"`

## Dependencies
You must execute the composer command adding the install parameter

`make composer c=install`

Additionally you can replace the `c` value with any composer option 

## DB && Migrations
You need configure the database .env config on src folder and run the migrate command

`make migrate`

## Using Artisan commands

Using the same configuration as composer you can execute the artisan commands passing a command in the `c` parameter

`make artisan c="key:generate"`

## Recreate Env

If you need recreate the env from start and set it ready to use you must run: 

`make recreate-env`