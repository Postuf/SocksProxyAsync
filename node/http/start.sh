#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
nohup $DIR/../node_modules/http-server/bin/http-server --p 8080 &
echo $! > ../../pid2.txt
