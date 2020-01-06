var named = require('./named/lib/index');
var server = named.createServer();
var ttl = 300;
var port = 9999;

server.listen(port, '127.0.0.1', function() {
  console.log('DNS server started on port ' + port);
});

server.on('query', function(query) {
  var domain = query.name();
  console.log('DNS Query: %s', domain)
  var target = new named.ARecord('127.0.0.1');
  query.addAnswer(domain, target, ttl);
  server.send(query);
});
