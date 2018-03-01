# library-rest-api
Front end for legacy RESTful services provided by Ingalls Library

## Prerequisites
- PHP 7
- Ruby 2.3+
- Web server (such as Apache 2.4)
- Worldcat API key

## Installation
Check out the repository to a local directory on your machine. If installing to a production server use [Capistrano](http://www.capistranorb.com) be sure to also follow the steps underneath *Remote Deployment*. Otherwise only the first steps below are required.

Mount the application within your web server. It is highly recommended not to simply drop it into the document root. Instead use an alias such as the one below for Apache 2.4

```
Alias /api /var/www/apps/library-rest-api/public
<Directory /var/www/apps/library-rest-api/public/>
  AllowOverride none
  Require all granted

  # If bridging to the legacy version
  RewriteEngine On
  RewriteBase /api

  RewriteRule ^/(.*)/$ /$1 [PT]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d

  # Redirects from legacy REST API to new endpoints
  RewriteRule rest/aleph/alephid/(.*)$ api/citation/aleph/$1 [R=301,L]
  RewriteRule rest/aleph/artist/(.*)$ api/opac/artist/$1 [R=301,L]
  RewriteRule rest/aleph/object/(.*)$ api/opac/object/$1 [R=301,L]
  RewriteRule rest/aleph/oclc/(.*)$ api/citation/oclc/$1 [R=301,L]
  RewriteRule rss/new_titles\.php$ api/opac/recent_titles [R=301,L]
  RewriteCond %{QUERY_STRING} (alephid|isbn|issn|lc|oclc)=(.*)
  RewriteRule services/cite/index\.php$ api/citation/%1/%2 [R=301,L]

  # Base rule for Laravel
  RewriteRule ^ index.php [L]
</Directory>
```

Configure .env with functional values for connecting to Ex Libris and
WorldCat.

```
APP_LOG_LEVEL = "info"

# Fully qualified URI to Ex Libris X server
ALEPH_API = "http://my-aleph-server/X/"
# Name of library to use for lookups
ALEPH_LIB = "lib01"

# Stylesheet to use for transforms between OAI MARC and MARC21 XML
OAI_STYLESHEET = "stylesheet.xsl"

# WorldCat key provided by OCLC for access to citation service
OCLC_KEY = 

# Link back to local library catalog
OPAC_URI = "http://opac.site.org"
```

Restart the web server and you shuld be able to begin querying the REST API
for information based on OCLC call numbers, accession numbers, and other
valid endpoints.

### Remote deployment ###
If deploying to a remote server for production use Capistrano. From your local
machine first make sure all gem dependencies are properly installed.

```
bundle install
```

Next you will want to make sure you have public / private keys set up to the production server. Agent forwarding is highly recommended so that Github can properly do pulls without having to repeatedly prompt for passwords. See the guide at [Digital Ocean](https://www.digitalocean.com/community/tutorials/how-to-set-up-ssh-keys--2) for more information.

Once you have validated your keys you can cold boot the application. This will set up the directory structure for future deployments.

```
cap production deploy
```

On your production server create _shared/.env_ to fill in the values and initialize the application. Do another deploy which this time should succeed. If so continue by mounting the application on the web server.

```
cap production deploy
```

## Usage
The application exposes several RESTful endpoints for consumption of library data. Depending on your rewrite rules the locations may be slightly different if you chose to hide _api_ from the URI.

### Citations
A Chicago style citation can be retrieved using the endpoints below. Substitute an actual value wherever curly braces indicate a placeholder.

- api/citation/aleph/{alephid}
- api/citation/isbn/{isbn}
- api/citation/issn/{issn}
- api/citaton/lc/{call_number}
- api/citation/oclc/{call_number}

### Library catalog searches
These endpoints will return a record count as well as a link back into the OPAC based on the query provided. All matches are partial - a search for "Mon" will match "Monet", "money", etc. Likewise a search for "1960" will return a count that includes all items associated with an accession number containing "1960".

- api/opac/artist/{artist}
- api/opac/object/{id}

### Recent titles feed
This endpoint generates a list of recently added items. Each record contains a link back into the library catalog.

- api/opac/recent_titles

## Testing
Code coverage is provided for most of the code using [PHPUnit](https://phpunit.de/). Laravel will install it as part of the deployment process if you are in a development environment. *For security purposes PHPUnit should never be installed to production.*

```
vendor/bin/phpunit tests/
```

When adding new functionality be sure to add tests underneath _tests/_ using the existing files as a template. Any errors should be resolved before merging new commits into master.
