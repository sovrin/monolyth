## monolyth structure
### usage
to install all dependencies:
`make install`

to run an app:
`make run-backend-core`

or
`make run-frontend-core`

## deployment
`make deploy APP:=core` // for core application
this creates an archive in the `backend/temp/deploy` folder

### notes

backend apps may need to be bundled (similar to frontend) into a single archive.
a small script could be used to analyze the dependencies in the app and bundle them.

additionally, the bundle could have hashes for each package to determine if the app has been updated and needs to be 
re-deployed.


### OpenAPI

To render the spec use

`php bin/console nelmio:apidoc:dump --format=json > apps/core/openapi.json`