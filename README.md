# SocksProxyAsync

Asynchronous SOCKS5 client library

## Requirements

* PHP 7.1+
* Composer
  * ext-sockets

## Testing
Tests require working proxy and http server to be up and running, use `node/proxy.js` to start proxy, `node/http/start.sh` (`./start.sh` within its subdir) to start http server.
By default, http server runs on port 8080, proxy occupies port 1080, tests use these ports.
