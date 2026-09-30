#!/bin/sh
# /usr/local/bin/hosteditor  (chmod +x this)
# launch-editor invokes this as: host-editor <filename> <line> <column>
filename="$1"
line="$2"
column="$3"

echo "Launching $filename $line $column"

curl -s -G "http://host.docker.internal:3334/open" \
  --data-urlencode "file=$filename" \
  --data-urlencode "line=$line" \
  --data-urlencode "column=$column" \
  > /dev/null
