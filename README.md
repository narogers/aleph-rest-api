# library-rest-api
Front end for legacy RESTful services provided by Ingalls Library

## Prerequisites
For this application to work properly you must have an existing WorldCat account and API key. It also requires a recent version of PHP (5.6+).

## Installation
Check out the repository into your web site. It is suggested that you mount the application using an Alias directive rather than directly installing the files underneath DocumentRoot. For more information on how to do this see the [Apache documenation](https://httpd.apache.org/docs/2.4/mod/mod_alias.html).

Set the environment keys which will allow for communication with external systems. To configure php-fpm you would add the following entries to the pool definition.

```
env[WORLDCAT_API] = "mySuperSecretAPIKey"
env[ALEPH_HOST] = "http://my.aleph.host"
env[ALEPH_X_ENDPOINT] = "/X?op=find&base=mylibrary"
env[DB_HOST] = "localhost"
env[DB_USERNAME] = "user"
env[DB_PASSWORD] = "password"
env[DB_DATABASE] = "database"
```

## Usage
The application exposes several RESTful endpoints for consumption of library data.

### Citation Resolution
_services/cite/index.php?isbn=[ISBN]_ will perform a lookup against OCLC WorldCat and return a properly formatted citation for the record.

### Library Catalog
Various endpoints are defined for access to search the catalog by different attributes including
- Accession Number (_aleph/object/+[ACCESSION_NUMBER]_)
- Aleph ID (_aleph/alephid/+[ALEPHID]_)
- Artist (_aleph/artist/+[ARTIST]_)
- OCLC Call Number (_aleph/oclc/+[OCLC_CALL_NUMBER]_)

In addition a list of recent acquisitions can be retrieved using the endpoint
- mondaytable (aleph/mondaytable)

### New Title Feed
_rss/new_titles.php_ generates a list of recently added titles from the Ingalls catalog. Information is pulled from Aleph using the X API.
