# check_solr_slave.php
Written by [Glenn Plas](http://byte-consult.be)

 - Uses curl for HTTP calls to the slave
 - Cleans out dangerous moves (basically, it only allow GET request to be sent to a slave)

## Overview
 - This script either parses or tails a solr request log file and sends appropriate slave requests. 
 - exits at the first error, so as long as it runs, the slave looks to respond fine

## Usage

./check_solr_slave.php -i /var/log/jetty/2013_01_04.request.log -s 192.168.128.31:8080 -t

1. `-i`, OR `--input <value>` : The input file to get the requests from (required)
2. `-s`, OR `--slave <value>` : Format is host[:port] ( host can be an ip or a hostname) (required)
3. `-t`, OR `--tail`          : Use tail on the file so you check in realtime, otherwise it will be read from start to end
4. `-d`, OR `--debug <value>` : Debug flag level. ( value range 1..6 ) => Unused for now

## Gotchas

Large files take a long time to process sequentially, I find the tail function a lot more interesting.  Your slave will get some hits.

## Dependencies
 - php curl !
 - solr (duh)

## Inspiration
 - A real life problem that required me to verify if a slave was doing fine.
## License

## TODO
 - Check the content of the responses
 - Check the response sizes of the slave and compair them to the master (Theoretically they should be the same)

Copyright (c) 2012, Byte Consult
All rights reserved.

See included License file
