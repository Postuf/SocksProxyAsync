#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

git submodule update --init

cd node/named
npm install
# we are in node
cd ..
npm install
# we are in root dir
cd ..

rm -f nohup.out node/http/nohup.out

if [ -f pid1.txt ]; then
  kill -9 `cat pid1.txt`
fi
if [ -f pid2.txt ]; then
  kill -9 `cat pid2.txt`
fi
if [ -f pid3.txt ]; then
  kill -9 `cat pid3.txt`
fi

node --version
nohup node node/named.js &
echo $! > pid3.txt
nohup node node/proxy.js &
echo $! > pid1.txt
cd node/http
nohup ./start.sh &
cd $DIR
sleep 5
echo proxy output:
tail nohup.out
echo http srv output:
tail node/http/nohup.out

echo "starting test..."
./vendor/bin/phpunit --configuration tests/phpunit.config.xml tests && kill -9 `cat pid1.txt` && kill -9 `cat pid2.txt` && kill -9 `cat pid3.txt` && rm -f pid1.txt pid2.txt pid3.txt
