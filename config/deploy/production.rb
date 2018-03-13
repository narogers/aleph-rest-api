set :stage, :production
set :branch, ENV.fetch("REVISION", 'master')
set :deploy_to, "/var/www/sites/library.clevelandart.org/apps/library-rest-api"

set :laravel_server_user, "apache"
set :laravel_artisan_flags, "--env=production"

set :composer_install_flags, "--no-dev --no-interaction --optimize-autoloader --prefer-dist"
set :composer_roles, :all
set :composer_dump_autoload, "--optimize"

set :file_permission_paths, [
  "storage/",
  "storage/framework",
  "storage/framework/cache",
  "storage/framework/sessions",
  "storage/framework/views",
  "storage/logs"
]

set :file_permission_users, ['apache', 'nrogers']
set :file_permission_groups, %w{web}

server "production.srv", user: "webapps", roles: %w{app db web}
