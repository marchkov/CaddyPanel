{panel_domain} {
    root * /opt/caddypanel/public

    encode zstd gzip

    redir /files /files/

    handle_path /files/* {
        root * /opt/caddypanel/apps/filegator/dist

        @static path /favicon.ico /manifest.json /robots.txt /service-worker.js /fonts/* /img/*
        handle @static {
            file_server
        }

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
