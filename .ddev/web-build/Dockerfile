# Enable modules
RUN a2enmod authn_file authn_dbm authn_anon authn_dbd authn_socache authn_core authz_host authz_groupfile authz_user authz_dbm authz_owner authz_core access_compat auth_basic socache_shmcb macro reqtimeout include filter substitute deflate brotli mime env expires headers unique_id setenvif proxy ssl status autoindex info vhost_alias dir userdir alias rewrite

# .ddev/web-build/Dockerfile.apache
# RUN a2dismod proxy_fcgi && a2enmod php* && apache2ctl graceful