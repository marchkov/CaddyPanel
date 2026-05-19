{panel_domain} {
    root * /opt/caddypanel/public

    encode zstd gzip

    redir /files /files/

    handle_path /files/* {
        forward_auth unix/{panel_php_fpm_socket} {
            uri /filegator-auth.php
            transport fastcgi {
                root /opt/caddypanel/public
            }
        }

        root * /opt/caddypanel/apps/filegator/dist
        try_files {path} /caddypanel.php
        php_fastcgi unix/{panel_php_fpm_socket}
        file_server
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
