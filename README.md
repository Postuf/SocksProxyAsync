# SocksProxyAsync

Asynchronous SOCKS5 client library

[![Build Status](https://travis-ci.org/Postuf/SocksProxyAsync.svg?branch=master)](https://travis-ci.org/Postuf/SocksProxyAsync)

## Requirements

* PHP 7.1+
* Composer
  * ext-sockets

## How it works

Say, you have a socket and an event loop:
```
$socket = new SocketAsync(/* ... */);
while(true) {
  // process events
  if (!$socket->ready()) {
    $socket->poll();
  } else {
    // your logic ...
  }
}
```

We create socket, set [socket_set_nonblock](https://www.php.net/manual/ru/function.socket-set-nonblock.php), when socket is connected, `isReady` flag is set.
Internal logic is organized as state machine. You can extend it and add more steps, so that only `$socket->poll()` is called on event loop, then you just check the state and process received updates.

## Testing

Tests require working proxy and http server to be up and running, use `node/proxy.js` to start proxy, `node/http/start.sh` (`./start.sh` within its subdir) to start http server.
By default, http server runs on port 8080, proxy occupies port 1080, tests use these ports.
