#=========================================================================#
# Laravel Nginx Template (HTTP) - Redirect to HTTPS Only                  #
# DO NOT MODIFY THIS FILE! CHANGES WILL BE LOST WHEN REBUILDING DOMAINS   #
# https://hestiacp.com/docs/server-administration/web-templates.html      #
#=========================================================================#

server {
    listen      %ip%:%web_port%;
    server_name %domain_idn% %alias_idn%;
    return 301 https://$host$request_uri;
}
