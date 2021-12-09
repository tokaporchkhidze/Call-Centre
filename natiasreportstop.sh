#!/bin/bash

PID=`ps -ef | grep "artisan queue:work database --memory=5120" | grep -v grep | awk '{print $2}'`

if [[ "" !=  "$PID" ]]; then
    kill -9 $PID
fi