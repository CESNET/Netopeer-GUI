#!/bin/bash

dir_base=$(pwd)
cmd_base='php app/console'

cp app/config/parameters.yml.dist app/config/parameters.yml
php vendor/sensio/distribution-bundle/Sensio/Bundle/DistributionBundle/Resources/bin/build_bootstrap.php
${cmd_base} cache:clear --env=prod
${cmd_base} assets:install --symlink --relative
${cmd_base} doctrine:database:create
${cmd_base} doctrine:generate:entities FIT
${cmd_base} doctrine:schema:update --force
${cmd_base} app:user --action=add --user=admin --pass=pass
${cmd_base} assetic:dump --env=prod --no-debug
${cmd_base} app:install --post=install
