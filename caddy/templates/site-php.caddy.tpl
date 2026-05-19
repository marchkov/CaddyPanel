{hosts} {
    root * {public_path}

    encode zstd gzip

    php_fastcgi unix/{php_fpm_socket}
    file_server

    log {
        output file {access_log} {
            roll_size {roll_size}
            roll_keep {roll_keep}
            roll_keep_for {roll_keep_for}
        }
    }
}
