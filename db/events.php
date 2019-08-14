<?php

$observers = array(
    array(
        'eventname'   => '*',
        'callback'    => '\local_dynamicaudience\audience::observe_all',
        'internal'    => false
    ),

);
