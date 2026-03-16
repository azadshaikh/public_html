#=========================================================================#
# Astero Nginx Template (HTTP) - Suspended Website                 #
# Redirects all requests to HTTPS                                         #
#=========================================================================#

server {
    listen      %ip%:%web_port%;
    server_name %domain_idn% %alias_idn%;
    return 301 https://$host$request_uri;
}
