set :stage, :staging
set :branch, ENV.fetch("REVISION", 'master')
set :deploy_to, "/var/www/sites/libdrupal/apps/library-rest-api"

set :laravel_server_user, "apache"

set :composer_install_flags, "--no-dev --no-interaction --optimize-autoloader --prefer-dist"
set :composer_roles, :all
set :composer_dump_autoload, "--optimize"

server "staging.srv", user: "webapps", roles: %w{app db web}
