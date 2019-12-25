#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

cd node
npm install
cd ..

rm -f nohup.out node/http/nohup.out

node --version
nohup node node/proxy.js &
echo $! > pid1.txt
cd node/http
nohup ./start.sh &
cd $DIR
sleep 5
tail nohup.out

./vendor/bin/phpunit tests && kill -9 `cat pid1.txt` && kill -9 `cat pid2.txt` && rm -f pid1.txt pid2.txt
