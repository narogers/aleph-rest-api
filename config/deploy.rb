# config valid only for current version of Capistrano
lock "3.10.1"

set :application, "library-rest-api"
set :repo_url, "git@github.com:ClevelandMuseumArt/library-rest-api.git"
set :laravel_version, 5.5

# Default branch is :master
# ask :branch, `git rev-parse --abbrev-ref HEAD`.chomp
ask :username, nil, echo: true
ask :password, nil, echo: false

# Default deploy_to directory is /var/www/my_app_name
#set :deploy_to, "/var/www/my_app_name"

# Default value for :format is :airbrussh.
set :format, :pretty

# You can configure the Airbrussh format using :format_options.
# These are the defaults.
# set :format_options, command_output: true, log_file: "log/capistrano.log", color: :auto, truncate: :auto

# Default value for :pty is false
# set :pty, true

# Default value for :linked_files is []
append :linked_files, ".env"

# Default value for linked_dirs is []
append :linked_dirs, "storage/logs"
set :laravel_upload_dotenv_file_on_deploy, false

# Default value for default_env is {}
# set :default_env, { path: "/opt/ruby/bin:$PATH" }

# Default value for local_user is ENV['USER']
# set :local_user, -> { `git config user.name`.chomp }

# Default value for keep_releases is 5
set :keep_releases, 5
# Forward user agent for pulls from Github
set :ssh_options, { :forward_agent => true }

namespace :deploy do
  before :starting, :map_composer_command do
    on roles(:app) do |server|
      SSHKit.config.command_map[:composer] = "#{shared_path.join("composer.phar")}"
    end
  end

  after :starting, 'composer:install_executable'
  after :updating, "composer:install"
end
