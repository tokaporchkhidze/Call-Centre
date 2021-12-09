#!/bin/bash

# This file should be ran as a service by natiasreport.service
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd $DIR
./artisan queue:work database --memory=5120
