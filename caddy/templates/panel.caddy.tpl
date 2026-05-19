{panel_domain} {
    root * /opt/caddypanel/public

    encode zstd gzip

    redir /files /files/

    handle /files/css/* {
        root * /opt/caddypanel/apps/filegator/dist
        uri strip_prefix /files
        file_server
    }

    handle /files/fonts/* {
        root * /opt/caddypanel/apps/filegator/dist
        uri strip_prefix /files
        file_server
    }

    handle /files/img/* {
        root * /opt/caddypanel/apps/filegator/dist
        uri strip_prefix /files
        file_server
    }

    handle /files/js/* {
        root * /opt/caddypanel/apps/filegator/dist
        uri strip_prefix /files
        file_server
    }

    @filegator_static path /files/favicon.ico /files/manifest.json /files/robots.txt /files/service-worker.js
    handle @filegator_static {
        root * /opt/caddypanel/apps/filegator/dist
        uri strip_prefix /files
        file_server
    }

    handle_path /files/* {
        root * /opt/caddypanel/apps/filegator/dist
        rewrite * /caddypanel.php
        php_fastcgi unix/{panel_php_fpm_socket}
    }

    php_fastcgi unix/{panel_php_fpm_socket}
    file_server

    log {
        output file /var/log/caddypanel/panel.access.log {
            roll_size 10MB
            roll_keep 10
            roll_keep_for 720h
        }
    }
}
